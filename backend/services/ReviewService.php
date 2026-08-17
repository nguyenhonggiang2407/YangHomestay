<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * REVIEW SERVICE
 *
 * Chỉ cho review khi: booking.customer_id = session customer
 *                     VÀ booking.status = 'checkout'
 *                     VÀ booking chưa có review (unique booking_id).
 * Review mới luôn status = 'pending' (KHÔNG auto approve).
 * Chỉ review 'approved' hiển thị công khai. "✓ Verified stay" suy ra từ booking.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ReviewService
{
    private SupabaseClient $db;

    private const RATING_FIELDS = [
        'overall_rating', 'cleanliness_rating', 'location_rating',
        'amenities_rating', 'accuracy_rating', 'communication_rating', 'value_rating',
    ];

    public function __construct()
    {
        $this->db = supabase();
    }

    public function create(int $customerId, array $data): array
    {
        try {
            $bookingId = Validation::nonNegativeInt('booking_id', $data['booking_id'] ?? null, 'đơn đặt');
            $title    = Validation::string('title', $data['title'] ?? '', 255, false, 'tiêu đề');
            $comment  = Validation::string('comment', $data['comment'] ?? '', 2000, false, 'nội dung đánh giá');
            $ratings = [];
            foreach (self::RATING_FIELDS as $field) {
                $ratings[$field] = Validation::intInRange($field, $data[$field] ?? null, 1, 5, 'điểm đánh giá');
            }
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        if ($title === '' && $comment === '') {
            return ['success' => false, 'message' => 'Vui lòng viết tiêu đề hoặc nội dung đánh giá.', 'status' => 422];
        }

        // Booking thuộc customer + đã checkout
        $res = $this->db->select('bookings', [
            'select'  => 'id,status,customer_id,unit_id',
            'filters' => [Pg::eqInt('id', $bookingId), Pg::eqInt('customer_id', $customerId)],
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('review', 'booking_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể xác minh kỳ nghỉ lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $booking = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($booking === null) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn đặt của bạn', 'status' => 404];
        }
        if (($booking['status'] ?? '') !== 'checkout') {
            return ['success' => false, 'message' => 'Chỉ có thể đánh giá sau khi hoàn tất kỳ nghỉ.', 'status' => 422];
        }

        // Chưa có review (unique booking_id bảo vệ thêm)
        $dup = $this->db->select('reviews', [
            'select'  => 'id',
            'filters' => Pg::eqInt('booking_id', $bookingId),
            'limit'   => 1,
        ]);
        if (!$dup['ok']) {
            Security::logError('review', 'duplicate_check_failed', ['status' => $dup['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể kiểm tra đánh giá hiện tại. Vui lòng thử lại.', 'status' => 503];
        }
        if (is_array($dup['data']) && count($dup['data']) > 0) {
            return ['success' => false, 'message' => 'Bạn đã đánh giá cho đơn này rồi.', 'status' => 409];
        }

        $payload = array_merge([
            'booking_id'  => $bookingId,
            'customer_id' => $customerId,
            'unit_id'     => (int)$booking['unit_id'],
            'title'       => $title !== '' ? $title : null,
            'comment'     => $comment !== '' ? $comment : null,
            'status'      => 'pending',
        ], $ratings);

        $ins = $this->db->insert('reviews', $payload, ['select' => 'id,status']);
        if (!$ins['ok']) {
            Security::logError('review', 'create_failed', ['status' => $ins['status']]);
            return ['success' => false, 'message' => 'Lỗi khi gửi đánh giá', 'status' => 503];
        }

        return ['success' => true, 'message' => 'Cảm ơn bạn! Đánh giá đang chờ kiểm duyệt.', 'data' => ['id' => $ins['data'][0]['id'] ?? null, 'status' => 'pending'], 'status' => 201];
    }

    /* ────────────────────────────────────────────────────────────────
     * ADMIN
     * ──────────────────────────────────────────────────────────────── */
    public function adminList(?string $status = null): array
    {
        $filters = [];
        $status = Pg::whitelist($status, ['pending', 'approved', 'rejected']);
        if ($status !== null) {
            $filters[] = Pg::eqStr('status', $status);
        }
        $res = $this->db->select('reviews', [
            'select'  => 'id,booking_id,customer_id,unit_id,overall_rating,title,comment,status,created_at,approved_at',
            'filters' => $filters,
            'order'   => 'created_at.desc',
            'limit'   => 200,
        ]);
        if (!$res['ok']) {
            Security::logError('review', 'admin_list_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải danh sách đánh giá lúc này.', 'status' => 503];
        }
        $reviews = is_array($res['data']) ? $res['data'] : [];

        // enrich customer + unit names
        $custIds = array_map('intval', array_column($reviews, 'customer_id'));
        $unitIds = array_map('intval', array_column($reviews, 'unit_id'));
        $custMap = $this->nameMap('customers', $custIds, 'full_name');
        $unitMap = $this->nameMap('units', $unitIds, 'name');
        foreach ($reviews as &$r) {
            $r['customer_name'] = $custMap[(int)$r['customer_id']] ?? null;
            $r['unit_name']     = $unitMap[(int)$r['unit_id']] ?? null;
        }

        return ['success' => true, 'data' => $reviews, 'status' => 200];
    }

    public function moderate(int $reviewId, string $status, int $adminId): array
    {
        $status = Pg::whitelist($status, ['approved', 'rejected']);
        if ($status === null) {
            return ['success' => false, 'message' => 'Trạng thái duyệt không hợp lệ.', 'status' => 422];
        }

        $res = $this->db->select('reviews', [
            'select'  => 'id,status',
            'filters' => Pg::eqInt('id', $reviewId),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('review', 'moderate_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải đánh giá lúc này.', 'status' => 503];
        }
        $review = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($review === null) {
            return ['success' => false, 'message' => 'Không tìm thấy đánh giá', 'status' => 404];
        }

        $payload = [
            'status'               => $status,
            'approved_at'          => $status === 'approved' ? gmdate('Y-m-d\TH:i:s\Z') : null,
            'approved_by_admin_id' => $adminId,
        ];
        $moderateFilters = implode('&', [
            Pg::eqInt('id', $reviewId),
            Pg::eqStr('status', (string)$review['status']),
        ]);
        $upd = $this->db->update('reviews', $payload, $moderateFilters);
        if (!$upd['ok']) {
            Security::logError('review', 'moderate_failed', ['status' => $upd['status']]);
            return ['success' => false, 'message' => 'Lỗi khi duyệt đánh giá', 'status' => 503];
        }
        if (!is_array($upd['data']) || count($upd['data']) === 0) {
            return ['success' => false, 'message' => 'Đánh giá vừa được xử lý ở nơi khác. Vui lòng tải lại.', 'status' => 409];
        }

        (new AdminBookingService())->audit($adminId, $status === 'approved' ? 'APPROVE_REVIEW' : 'REJECT_REVIEW', 'reviews', $reviewId,
            ['status' => $review['status']], ['status' => $status]);

        return ['success' => true, 'message' => 'Đã ' . ($status === 'approved' ? 'duyệt' : 'từ chối') . ' đánh giá', 'status' => 200];
    }

    private function nameMap(string $table, array $ids, string $nameCol): array
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids)), fn($id) => $id > 0));
        if (!$ids) {
            return [];
        }
        $res = $this->db->select($table, [
            'select'  => 'id,' . $nameCol,
            'filters' => Pg::inInts('id', $ids),
            'limit'   => 100,
        ]);
        $map = [];
        foreach (($res['ok'] ? $res['data'] : []) as $row) {
            $map[(int)$row['id']] = $row[$nameCol] ?? null;
        }
        return $map;
    }
}
