<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * BOOKING SERVICE — khách hàng
 *
 * • create: gọi RPC create_booking_atomic (ATOMIC — advisory lock + overlap check
 *   ngay trong transaction). customer_id luôn lấy từ session, KHÔNG từ browser.
 * • my-bookings / booking-detail: luôn filter theo customer_id session.
 * • cancel: chỉ pending|confirmed → cancelled.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class BookingService
{
    private SupabaseClient $db;
    private ?string $enrichError = null;

    public function __construct()
    {
        $this->db = supabase();
    }

    /**
     * Tạo booking. $customerId từ session (controller truyền vào).
     */
    public function create(int $customerId, array $data): array
    {
        try {
            $unitId   = Validation::nonNegativeInt('unit_id', $data['unit_id'] ?? null, 'căn homestay');
            $checkIn  = Validation::date('check_in', $data['check_in'] ?? '');
            $checkOut = Validation::date('check_out', $data['check_out'] ?? '');
            $adults   = Validation::intInRange('adults', $data['adults'] ?? 1, 1, 20, 'số người lớn');
            $children = Validation::intInRange('children', $data['children'] ?? 0, 0, 20, 'số trẻ em');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $dateErr = AvailabilityService::validateDates($checkIn, $checkOut);
        if ($dateErr !== null) {
            return ['success' => false, 'message' => $dateErr, 'status' => 422];
        }

        $specialRequests = Security::limitInput($data['special_requests'] ?? '', 2000);
        $promoCode = trim((string)($data['promotion_code'] ?? ''));

        $params = [
            'p_customer_id'      => $customerId,
            'p_unit_id'          => $unitId,
            'p_check_in'         => $checkIn,
            'p_check_out'        => $checkOut,
            'p_adults'           => $adults,
            'p_children'         => $children,
            'p_special_requests' => $specialRequests !== '' ? $specialRequests : null,
            'p_promotion_code'   => $promoCode !== '' ? $promoCode : null,
        ];

        $res = $this->db->rpc('create_booking_atomic', $params);

        if ($res['ok']) {
            return ['success' => true, 'message' => 'Đã tạo yêu cầu đặt chỗ', 'data' => $res['data'], 'status' => 201];
        }

        return $this->mapRpcError($res);
    }

    /**
     * Map lỗi RPC (YBxxx) → HTTP + message thân thiện.
     */
    private function mapRpcError(array $res): array
    {
        $code = is_array($res['data'] ?? null) ? ($res['data']['code'] ?? null) : null;

        switch ($code) {
            case 'YB001':
                return ['success' => false, 'message' => 'Không tìm thấy căn homestay.', 'status' => 404];
            case 'YB002':
                return ['success' => false, 'message' => 'Khoảng ngày không hợp lệ.', 'status' => 422];
            case 'YB003':
                return ['success' => false, 'message' => 'Số khách vượt sức chứa của căn này.', 'status' => 422];
            case 'YB004':
            case 'YB005':
                return [
                    'success' => false,
                    'message' => 'Căn homestay vừa được đặt trong khoảng thời gian này. Vui lòng chọn ngày khác.',
                    'status'  => 409,
                ];
            case 'YB006':
                return ['success' => false, 'message' => 'Mã ưu đãi không hợp lệ hoặc đã hết hạn.', 'status' => 422];
            case 'YB007':
                return ['success' => false, 'message' => 'Mã ưu đãi đã hết lượt sử dụng.', 'status' => 422];
            case 'YB008':
                return ['success' => false, 'message' => 'Phiên đăng nhập không hợp lệ.', 'status' => 401];
        }

        Security::logError('booking', 'rpc_error', ['status' => $res['status'], 'code' => $code]);
        return ['success' => false, 'message' => 'Lỗi máy chủ, vui lòng thử lại', 'status' => 503];
    }

    /* ────────────────────────────────────────────────────────────────
     * MY BOOKINGS / DETAIL / CANCEL
     * ──────────────────────────────────────────────────────────────── */
    public function myBookings(int $customerId, ?string $statusFilter = null): array
    {
        $filters = [Pg::eqInt('customer_id', $customerId)];
        $statusFilter = Pg::whitelist($statusFilter, ['pending', 'confirmed', 'checkin', 'checkout', 'cancelled']);
        if ($statusFilter !== null) {
            $filters[] = Pg::eqStr('status', $statusFilter);
        }

        $res = $this->db->select('bookings', [
            'select'  => 'id,booking_reference,unit_id,check_in,check_out,number_of_nights,adults,children,total_guests,subtotal,cleaning_fee,discount_amount,total_amount,currency,promotion_code_snapshot,status,payment_status,special_requests,created_at,confirmed_at,checked_in_at,checked_out_at,cancelled_at',
            'filters' => $filters,
            'order'   => 'created_at.desc',
            'limit'   => 100,
        ]);
        if (!$res['ok']) {
            Security::logError('booking', 'my_bookings_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải đơn đặt lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $bookings = is_array($res['data']) ? $res['data'] : [];

        $bookings = $this->enrichList($bookings);
        if ($this->enrichError !== null) {
            Security::logError('booking', 'my_bookings_enrich_failed', ['reason' => $this->enrichError]);
            return ['success' => false, 'message' => 'Chưa thể tải đầy đủ thông tin căn cho đơn đặt lúc này. Vui lòng thử lại.', 'status' => 503];
        }

        return ['success' => true, 'data' => $bookings, 'status' => 200];
    }

    /**
     * Chi tiết booking — CHỈ khi thuộc customer (filter theo customer_id ở server).
     */
    public function detail(int $customerId, int $bookingId): array
    {
        $res = $this->db->select('bookings', [
            'select'  => '*',
            'filters' => [Pg::eqInt('id', $bookingId), Pg::eqInt('customer_id', $customerId)],
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('booking', 'detail_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải chi tiết đơn đặt lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $booking = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($booking === null) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn đặt của bạn', 'status' => 404];
        }

        $booking = $this->enrichOne($booking);
        if ($this->enrichError !== null) {
            Security::logError('booking', 'detail_enrich_failed', ['reason' => $this->enrichError]);
            return ['success' => false, 'message' => 'Chưa thể tải đầy đủ thông tin căn cho đơn đặt lúc này. Vui lòng thử lại.', 'status' => 503];
        }

        $nights = $this->db->select('booking_nights', [
            'select'  => 'stay_date,nightly_rate,rate_rule_id',
            'filters' => Pg::eqInt('booking_id', $bookingId),
            'order'   => 'stay_date.asc',
            'limit'   => 100,
        ]);
        if (!$nights['ok']) {
            Security::logError('booking', 'detail_nights_failed', ['status' => $nights['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải bảng giá của đơn lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $booking['nights'] = is_array($nights['data']) ? $nights['data'] : [];

        $items = $this->db->select('booking_price_items', [
            'select'  => 'item_type,label,amount,display_order',
            'filters' => Pg::eqInt('booking_id', $bookingId),
            'order'   => 'display_order.asc',
            'limit'   => 20,
        ]);
        if (!$items['ok']) {
            Security::logError('booking', 'detail_price_items_failed', ['status' => $items['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải chi tiết giá của đơn lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $booking['price_items'] = is_array($items['data']) ? $items['data'] : [];

        return ['success' => true, 'data' => $booking, 'status' => 200];
    }

    /**
     * Hủy booking — customer. Chỉ pending|confirmed → cancelled.
     */
    public function cancel(int $customerId, int $bookingId, string $reason = ''): array
    {
        $res = $this->db->select('bookings', [
            'select'  => 'id,status,payment_status,check_in',
            'filters' => [Pg::eqInt('id', $bookingId), Pg::eqInt('customer_id', $customerId)],
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('booking', 'cancel_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể kiểm tra đơn đặt lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $booking = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($booking === null) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn đặt của bạn', 'status' => 404];
        }

        if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
            return ['success' => false, 'message' => 'Không thể hủy đơn đặt ở trạng thái hiện tại.', 'status' => 422];
        }

        $reason = Security::limitInput($reason, 500);
        // Optimistic concurrency guard: chỉ hủy nếu booking vẫn thuộc customer
        // và vẫn ở đúng trạng thái vừa đọc. Tránh ghi đè một check-in/checkout
        // vừa được nhân viên cập nhật ở request khác.
        $cancelFilters = implode('&', [
            Pg::eqInt('id', $bookingId),
            Pg::eqInt('customer_id', $customerId),
            Pg::eqStr('status', (string)$booking['status']),
        ]);
        $upd = $this->db->update('bookings', [
            'status'             => 'cancelled',
            'cancelled_at'       => gmdate('Y-m-d\TH:i:s\Z'),
            'cancellation_reason'=> $reason !== '' ? $reason : null,
        ], $cancelFilters);

        if (!$upd['ok']) {
            Security::logError('booking', 'cancel_failed', ['status' => $upd['status']]);
            return ['success' => false, 'message' => 'Chưa thể hủy đơn lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        if (!is_array($upd['data']) || count($upd['data']) === 0) {
            return [
                'success' => false,
                'message' => 'Trạng thái đơn vừa thay đổi. Vui lòng tải lại trước khi hủy.',
                'status' => 409,
            ];
        }

        return ['success' => true, 'message' => 'Đã hủy đơn đặt', 'data' => ['id' => $bookingId, 'status' => 'cancelled'], 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * ENRICH — unit + property + cover
     * ──────────────────────────────────────────────────────────────── */
    private function enrichList(array $bookings): array
    {
        $unitIds = array_map('intval', array_column($bookings, 'unit_id'));
        $unitMap = $this->unitMap($unitIds);
        foreach ($bookings as &$b) {
            $u = $unitMap[(int)$b['unit_id']] ?? null;
            $b['unit'] = $u;
        }
        return $bookings;
    }

    private function enrichOne(array $booking): array
    {
        $list = $this->enrichList([$booking]);
        return $list[0];
    }

    private function unitMap(array $unitIds): array
    {
        $this->enrichError = null;
        $unitIds = array_values(array_filter(array_unique($unitIds), fn($id) => $id > 0));
        if (!$unitIds) {
            return [];
        }
        $res = $this->db->select('units', [
            'select'  => 'id,name,slug,type,property_id,base_price,max_guests',
            'filters' => Pg::inInts('id', $unitIds),
            'limit'   => 50,
        ]);
        if (!$res['ok']) {
            $this->enrichError = 'units';
            return [];
        }
        $units = is_array($res['data']) ? $res['data'] : [];

        $propIds = array_map('intval', array_column($units, 'property_id'));
        $propMap = [];
        if ($propIds) {
            $pr = $this->db->select('properties', [
                'select'  => 'id,name,slug,district',
                'filters' => Pg::inInts('id', $propIds),
                'limit'   => 50,
            ]);
            if (!$pr['ok']) {
                $this->enrichError = 'properties';
                return [];
            }
            foreach ((is_array($pr['data']) ? $pr['data'] : []) as $p) {
                $propMap[(int)$p['id']] = $p;
            }
        }

        // cover images
        $imgMap = [];
        $ir = $this->db->select('unit_images', [
            'select'  => 'unit_id,image_url,caption,is_cover,display_order',
            'filters' => Pg::inInts('unit_id', $unitIds),
            'order'   => 'is_cover.desc,display_order.asc',
            'limit'   => 300,
        ]);
        if (!$ir['ok']) {
            $this->enrichError = 'unit_images';
            return [];
        }
        foreach ((is_array($ir['data']) ? $ir['data'] : []) as $img) {
            if (!isset($imgMap[(int)$img['unit_id']])) {
                $imgMap[(int)$img['unit_id']] = $img;
            }
        }

        $map = [];
        foreach ($units as $u) {
            $u['property'] = $propMap[(int)$u['property_id']] ?? null;
            $u['cover_image'] = $imgMap[(int)$u['id']] ?? null;
            $map[(int)$u['id']] = $u;
        }
        return $map;
    }
}
