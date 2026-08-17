<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * AVAILABILITY SERVICE
 *
 * Quy ước: check_in inclusive / check_out exclusive (20/08 → 23/08 = 3 đêm).
 * Overlap: new_check_in < existing_check_out AND new_check_out > existing_check_in.
 * Blocking: pending|confirmed|checkin · Không blocking: checkout|cancelled.
 * Ngoài booking còn kiểm tra availability_blocks (maintenance, owner_use...).
 *
 * REUSE database function is_unit_available() qua /rpc (đã có trong 005_functions.sql).
 * ═════════════════════════════════════════════════════════════════════════
 */
final class AvailabilityService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    /**
     * Validate + kiểm tra availability.
     *
     * @return array success + data {unit_id, check_in, check_out, available, reasons[]}
     */
    public function check(int $unitId, string $checkIn, string $checkOut): array
    {
        $err = self::validateDates($checkIn, $checkOut);
        if ($err !== null) {
            return ['success' => false, 'message' => $err, 'status' => 422];
        }

        $res = $this->db->rpc('is_unit_available', [
            'p_unit_id'   => $unitId,
            'p_check_in'  => $checkIn,
            'p_check_out' => $checkOut,
        ]);

        if (!$res['ok']) {
            Security::logError('availability', 'rpc_failed', ['status' => $res['status']]);
            return ['success' => false, 'message' => 'Lỗi kiểm tra availability', 'status' => 503];
        }

        $available = (bool)$res['data'];
        $reasons = [];
        if (!$available) {
            $reasons[] = 'Có booking hoặc lịch chặn trong khoảng ngày này.';
        }

        return [
            'success' => true,
            'data' => [
                'unit_id'   => $unitId,
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'available' => $available,
                'reasons'   => $reasons,
            ],
            'status' => 200,
        ];
    }

    /**
     * Validate dates thuần (testable). Trả message lỗi hoặc null nếu OK.
     */
    public static function validateDates(string $checkIn, string $checkOut): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d', $checkIn);
        if (!$d || $d->format('Y-m-d') !== $checkIn) {
            return 'Ngày nhận phòng không hợp lệ (YYYY-MM-DD).';
        }
        $o = DateTime::createFromFormat('Y-m-d', $checkOut);
        if (!$o || $o->format('Y-m-d') !== $checkOut) {
            return 'Ngày trả phòng không hợp lệ (YYYY-MM-DD).';
        }
        if ($checkOut <= $checkIn) {
            return 'Ngày trả phòng phải sau ngày nhận phòng.';
        }
        $today = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');
        if ($checkIn < $today) {
            return 'Ngày nhận phòng phải là hôm nay hoặc trong tương lai.';
        }
        return null;
    }

    /**
     * Trả về danh sách unit_id khả dụng (dùng cho search có dates).
     */
    public function availableUnitIds(string $checkIn, string $checkOut): array
    {
        $res = $this->db->rpc('available_units', [
            'p_check_in'  => $checkIn,
            'p_check_out' => $checkOut,
        ]);
        if (!$res['ok']) {
            return [];
        }
        $ids = [];
        foreach ((array)$res['data'] as $row) {
            if (is_array($row) && isset($row['unit_id'])) {
                $ids[] = (int)$row['unit_id'];
            }
        }
        return $ids;
    }
}
