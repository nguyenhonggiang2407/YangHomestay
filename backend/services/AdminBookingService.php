<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * ADMIN BOOKING SERVICE — quản lý booking cho admin
 *
 * State machine (backend enforce, KHÔNG cho status tùy ý):
 *   pending → confirmed | cancelled
 *   confirmed → checkin | cancelled
 *   checkin → checkout
 * Mọi thay đổi quan trọng ghi audit_logs (old/new JSON).
 * ═════════════════════════════════════════════════════════════════════════
 */
final class AdminBookingService
{
    private SupabaseClient $db;

    public const BOOKING_STATUSES = ['pending', 'confirmed', 'checkin', 'checkout', 'cancelled'];
    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid', 'refunded'];

    private const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['checkin', 'cancelled'],
        'checkin'   => ['checkout'],
    ];

    public function __construct()
    {
        $this->db = supabase();
    }

    public function list(array $f, int $page = 1, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $page  = max(1, $page);

        $filters = [];
        $status = Pg::whitelist($f['status'] ?? null, self::BOOKING_STATUSES);
        if ($status !== null) {
            $filters[] = Pg::eqStr('status', $status);
        }
        $payment = Pg::whitelist($f['payment_status'] ?? null, self::PAYMENT_STATUSES);
        if ($payment !== null) {
            $filters[] = Pg::eqStr('payment_status', $payment);
        }

        $res = $this->db->select('bookings', [
            'select'  => 'id,booking_reference,customer_id,unit_id,guest_name,guest_email,guest_phone,check_in,check_out,number_of_nights,total_guests,subtotal,cleaning_fee,discount_amount,total_amount,currency,status,payment_status,created_at,confirmed_at,checked_in_at,checked_out_at,cancelled_at',
            'filters' => $filters,
            'order'   => 'created_at.desc',
            'count'   => true,
            'limit'   => $limit,
            'offset'  => ($page - 1) * $limit,
        ]);

        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi tải danh sách booking', 'status' => 503];
        }
        $items = $res['data'] ?? [];
        $total = $res['count'] ?? count($items);

        // search theo reference/guest (PHP trên trang hiện tại — dataset nhỏ)
        if (!empty($f['search'])) {
            $q = mb_strtolower(trim($f['search']));
            $items = array_values(array_filter($items, function ($b) use ($q) {
                $hay = mb_strtolower(($b['booking_reference'] ?? '') . ' ' . ($b['guest_name'] ?? '') . ' ' . ($b['guest_email'] ?? '') . ' ' . ($b['guest_phone'] ?? ''));
                return str_contains($hay, $q);
            }));
            $total = count($items);
        }

        // enrich unit/property
        $svc = new BookingService();
        $items = $this->enrichList($items);

        return [
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int)ceil($total / $limit)],
            ],
            'status' => 200,
        ];
    }

    private function enrichList(array $bookings): array
    {
        $unitIds = array_map('intval', array_column($bookings, 'unit_id'));
        $unitIds = array_values(array_filter(array_unique($unitIds), fn($id) => $id > 0));
        $unitMap = [];
        if ($unitIds) {
            $ur = $this->db->select('units', [
                'select'  => 'id,name,slug,type,property_id',
                'filters' => Pg::inInts('id', $unitIds),
                'limit'   => 100,
            ]);
            $units = $ur['ok'] ? $ur['data'] : [];
            $propIds = array_map('intval', array_column($units, 'property_id'));
            $propMap = [];
            if ($propIds) {
                $pr = $this->db->select('properties', [
                    'select'  => 'id,name,slug,district',
                    'filters' => Pg::inInts('id', $propIds),
                    'limit'   => 100,
                ]);
                foreach (($pr['ok'] ? $pr['data'] : []) as $p) {
                    $propMap[(int)$p['id']] = $p;
                }
            }
            foreach ($units as $u) {
                $u['property'] = $propMap[(int)$u['property_id']] ?? null;
                $unitMap[(int)$u['id']] = $u;
            }
        }
        foreach ($bookings as &$b) {
            $b['unit'] = $unitMap[(int)$b['unit_id']] ?? null;
        }
        return $bookings;
    }

    public function detail(int $bookingId): array
    {
        $res = $this->db->select('bookings', [
            'select'  => '*',
            'filters' => Pg::eqInt('id', $bookingId),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('admin_booking', 'detail_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải booking lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $booking = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($booking === null) {
            return ['success' => false, 'message' => 'Không tìm thấy booking', 'status' => 404];
        }

        $list = $this->enrichList([$booking]);
        $booking = $list[0];

        $nights = $this->db->select('booking_nights', [
            'select'  => 'stay_date,nightly_rate,rate_rule_id',
            'filters' => Pg::eqInt('booking_id', $bookingId),
            'order'   => 'stay_date.asc',
            'limit'   => 100,
        ]);
        if (!$nights['ok']) {
            Security::logError('admin_booking', 'detail_nights_failed', ['status' => $nights['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải bảng giá booking lúc này.', 'status' => 503];
        }
        $booking['nights'] = is_array($nights['data']) ? $nights['data'] : [];

        $items = $this->db->select('booking_price_items', [
            'select'  => 'item_type,label,amount,display_order',
            'filters' => Pg::eqInt('booking_id', $bookingId),
            'order'   => 'display_order.asc',
            'limit'   => 20,
        ]);
        if (!$items['ok']) {
            Security::logError('admin_booking', 'detail_items_failed', ['status' => $items['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải chi tiết giá booking lúc này.', 'status' => 503];
        }
        $booking['price_items'] = is_array($items['data']) ? $items['data'] : [];

        return ['success' => true, 'data' => $booking, 'status' => 200];
    }

    /**
     * Update status theo state machine + audit.
     */
    public function updateStatus(int $bookingId, string $newStatus, int $adminId): array
    {
        $newStatus = Pg::whitelist($newStatus, self::BOOKING_STATUSES);
        if ($newStatus === null) {
            return ['success' => false, 'message' => 'Trạng thái không hợp lệ.', 'status' => 422];
        }

        $res = $this->db->select('bookings', [
            'select'  => 'id,status',
            'filters' => Pg::eqInt('id', $bookingId),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('admin_booking', 'status_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể kiểm tra trạng thái booking lúc này.', 'status' => 503];
        }
        $booking = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($booking === null) {
            return ['success' => false, 'message' => 'Không tìm thấy booking', 'status' => 404];
        }

        $current = (string)$booking['status'];
        if ($current === $newStatus) {
            return ['success' => true, 'message' => 'Không có thay đổi', 'status' => 200];
        }
        $allowed = self::TRANSITIONS[$current] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return ['success' => false, 'message' => 'Không thể chuyển trạng thái từ ' . $current . ' sang ' . $newStatus . '.', 'status' => 422];
        }

        $payload = ['status' => $newStatus];
        $now = gmdate('Y-m-d\TH:i:s\Z');
        if ($newStatus === 'confirmed') {
            $payload['confirmed_at'] = $now;
        } elseif ($newStatus === 'checkin') {
            $payload['checked_in_at'] = $now;
        } elseif ($newStatus === 'checkout') {
            $payload['checked_out_at'] = $now;
        } elseif ($newStatus === 'cancelled') {
            $payload['cancelled_at'] = $now;
        }

        $statusFilters = implode('&', [
            Pg::eqInt('id', $bookingId),
            Pg::eqStr('status', $current),
        ]);
        $upd = $this->db->update('bookings', $payload, $statusFilters);
        if (!$upd['ok']) {
            Security::logError('admin_booking', 'status_update_failed', ['status' => $upd['status']]);
            return ['success' => false, 'message' => 'Lỗi cập nhật trạng thái', 'status' => 503];
        }
        if (!is_array($upd['data']) || count($upd['data']) === 0) {
            return ['success' => false, 'message' => 'Booking vừa được cập nhật ở nơi khác. Vui lòng tải lại trước khi đổi trạng thái.', 'status' => 409];
        }

        $this->audit($adminId, 'CHANGE_BOOKING_STATUS', 'bookings', $bookingId,
            ['status' => $current], ['status' => $newStatus]);

        return ['success' => true, 'message' => 'Đã cập nhật trạng thái', 'data' => ['id' => $bookingId, 'status' => $newStatus], 'status' => 200];
    }

    public function updatePaymentStatus(int $bookingId, string $paymentStatus, int $adminId): array
    {
        $paymentStatus = Pg::whitelist($paymentStatus, self::PAYMENT_STATUSES);
        if ($paymentStatus === null) {
            return ['success' => false, 'message' => 'Trạng thái thanh toán không hợp lệ.', 'status' => 422];
        }

        $res = $this->db->select('bookings', [
            'select'  => 'id,payment_status',
            'filters' => Pg::eqInt('id', $bookingId),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('admin_booking', 'payment_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể kiểm tra trạng thái thanh toán lúc này.', 'status' => 503];
        }
        $booking = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($booking === null) {
            return ['success' => false, 'message' => 'Không tìm thấy booking', 'status' => 404];
        }

        $current = (string)($booking['payment_status'] ?? 'unpaid');
        $paymentFilters = implode('&', [
            Pg::eqInt('id', $bookingId),
            Pg::eqStr('payment_status', $current),
        ]);
        $upd = $this->db->update('bookings', ['payment_status' => $paymentStatus], $paymentFilters);
        if (!$upd['ok']) {
            Security::logError('admin_booking', 'payment_update_failed', ['status' => $upd['status']]);
            return ['success' => false, 'message' => 'Lỗi cập nhật thanh toán', 'status' => 503];
        }
        if (!is_array($upd['data']) || count($upd['data']) === 0) {
            return ['success' => false, 'message' => 'Trạng thái thanh toán vừa thay đổi ở nơi khác. Vui lòng tải lại.', 'status' => 409];
        }

        $this->audit($adminId, 'CHANGE_PAYMENT_STATUS', 'bookings', $bookingId,
            ['payment_status' => $current], ['payment_status' => $paymentStatus]);

        return ['success' => true, 'message' => 'Đã cập nhật trạng thái thanh toán', 'status' => 200];
    }

    /**
     * Ghi audit_logs (KHÔNG log password/secret).
     */
    public function audit(int $adminId, string $action, string $entityType, int $entityId, array $old, array $new): void
    {
        $this->db->insert('audit_logs', [
            'admin_id'       => $adminId,
            'action'         => $action,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'old_value_json' => json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_value_json' => json_encode($new, JSON_UNESCAPED_UNICODE),
            'ip_address'     => Security::clientIp(),
            'created_at'     => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /* ────────────────────────────────────────────────────────────────
     * DASHBOARD KPI / REVENUE / CALENDAR
     * ──────────────────────────────────────────────────────────────── */
    public function dashboard(string $role = 'super_admin'): array
    {
        $props = $this->countOf('properties', 'status=eq.active');
        $units = $this->countOf('units', 'status=eq.active');

        $bookingsByStatus = [];
        foreach (self::BOOKING_STATUSES as $s) {
            $bookingsByStatus[$s] = $this->countOf('bookings', Pg::eqStr('status', $s));
        }

        $today = date('Y-m-d');
        $checkinsToday  = $this->countOf('bookings', ['status=eq.checkin', Pg::eqStr('check_in', $today)]);
        $checkoutsToday = $this->countOf('bookings', ['status=eq.checkout', Pg::eqStr('check_out', $today)]);

        $monthStart = date('Y-m-01');
        $yearStart  = date('Y-01-01');

        $showRevenue = $role !== 'staff';
        $revenueToday  = $showRevenue ? $this->revenueSince($today) : null;
        $revenueMonth  = $showRevenue ? $this->revenueSince($monthStart) : null;
        $revenueYear   = $showRevenue ? $this->revenueSince($yearStart) : null;
        $revenue6m     = $showRevenue ? $this->revenueByMonth(6) : [];

        return [
            'success' => true,
            'data' => [
                'properties_total' => $props,
                'units_total'      => $units,
                'bookings_by_status' => $bookingsByStatus,
                'pending_bookings' => $bookingsByStatus['pending'] ?? 0,
                'confirmed_bookings' => $bookingsByStatus['confirmed'] ?? 0,
                'checkins_today'   => $checkinsToday,
                'checkouts_today'  => $checkoutsToday,
                'revenue_today'    => $revenueToday,
                'revenue_month'    => $revenueMonth,
                'revenue_year'     => $revenueYear,
                'revenue_6m'       => $revenue6m,
                'occupancy_rate'   => $this->occupancyRate(),
                'cancellation_rate'=> $showRevenue ? $this->cancellationRate() : null,
                'revenue_visible' => $showRevenue,
            ],
            'status' => 200,
        ];
    }

    private function countOf(string $table, $filters): int
    {
        $res = $this->db->select($table, ['select' => 'id', 'filters' => $filters, 'count' => true]);
        return $res['ok'] && $res['count'] !== null ? $res['count'] : 0;
    }

    /** Revenue: tổng total_amount booking status checkout/checkin + payment paid/partial? Chỉ tính paid. */
    private function revenueSince(string $sinceDate): int
    {
        $res = $this->db->select('bookings', [
            'select'  => 'total_amount',
            'filters' => [Pg::eqStr('payment_status', 'paid'), 'created_at=gte.' . $sinceDate],
            'limit'   => 1000,
        ]);
        $total = 0;
        foreach (($res['ok'] ? $res['data'] : []) as $b) {
            $total += (int)($b['total_amount'] ?? 0);
        }
        return $total;
    }

    /** Revenue theo tháng (6 tháng gần nhất) — chỉ booking payment_status=paid. */
    private function revenueByMonth(int $months): array
    {
        $out = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $now->modify("first day of -{$i} month");
            $start = $month->format('Y-m-d');
            $end = $month->modify('+1 month')->format('Y-m-d');
            $res = $this->db->select('bookings', [
                'select'  => 'total_amount',
                'filters' => [Pg::eqStr('payment_status', 'paid'), 'created_at=gte.' . $start, 'created_at=lt.' . $end],
                'limit'   => 1000,
            ]);
            $total = 0;
            foreach (($res['ok'] ? $res['data'] : []) as $b) {
                $total += (int)($b['total_amount'] ?? 0);
            }
            $out[] = ['month' => $month->format('Y-m'), 'label' => $month->format('m/Y'), 'revenue' => $total];
        }
        return $out;
    }

    /** Occupancy: đêm đã book (active) / (units × 30) xấp xỉ. */
    private function occupancyRate(): float
    {
        $units = $this->countOf('units', 'status=eq.active');
        if ($units === 0) {
            return 0.0;
        }
        $monthStart = date('Y-m-01');
        $nextMonth = (new DateTimeImmutable($monthStart))->modify('+1 month')->format('Y-m-d');
        $res = $this->db->select('bookings', [
            'select'  => 'check_in,check_out',
            'filters' => [
                'status=in.(pending,confirmed,checkin)',
                'check_in=lt.' . $nextMonth,
                'check_out=gt.' . $monthStart,
            ],
            'limit'   => 1000,
        ]);
        $bookedNights = 0;
        foreach (($res['ok'] ? $res['data'] : []) as $b) {
            $ci = new DateTimeImmutable($b['check_in']);
            $co = new DateTimeImmutable($b['check_out']);
            // clamp vào tháng
            $from = max($ci, new DateTimeImmutable($monthStart));
            $to = min($co, new DateTimeImmutable($nextMonth));
            if ($to > $from) {
                $bookedNights += $to->diff($from)->days;
            }
        }
        $capacity = $units * 30;
        return $capacity > 0 ? round($bookedNights / $capacity * 100, 1) : 0.0;
    }

    private function cancellationRate(): float
    {
        $total = $this->countOf('bookings', []);
        if ($total === 0) {
            return 0.0;
        }
        $cancelled = $this->countOf('bookings', 'status=eq.cancelled');
        return round($cancelled / $total * 100, 1);
    }

    /**
     * Reservation calendar: bookings active trong khoảng [start,end] theo unit.
     */
    public function calendar(string $start, string $end): array
    {
        $res = $this->db->select('bookings', [
            'select'  => 'id,booking_reference,unit_id,check_in,check_out,status,payment_status,guest_name,total_guests,total_amount',
            'filters' => [
                'status=in.(pending,confirmed,checkin)',
                'check_in=lt.' . $end,
                'check_out=gt.' . $start,
            ],
            'limit'   => 1000,
        ]);
        $bookings = $res['ok'] ? $res['data'] : [];

        $units = $this->db->select('units', [
            'select'  => 'id,code,name,status',
            'filters' => 'status=in.(active,maintenance)',
            'limit'   => 200,
        ]);
        $unitList = $units['ok'] ? $units['data'] : [];

        $blocks = $this->db->select('availability_blocks', [
            'select'  => 'id,unit_id,start_date,end_date,reason,note',
            'filters' => ['start_date=lt.' . $end, 'end_date=gt.' . $start],
            'limit'   => 500,
        ]);
        $blockList = $blocks['ok'] ? $blocks['data'] : [];

        return [
            'success' => true,
            'data' => [
                'start' => $start,
                'end'   => $end,
                'bookings' => $bookings,
                'blocks'   => $blockList,
                'units'    => $unitList,
            ],
            'status' => 200,
        ];
    }

    public function todayOperations(): array
    {
        $today = date('Y-m-d');
        $arrivals = $this->db->select('bookings', [
            'select' => 'id,booking_reference,unit_id,guest_name,guest_email,guest_phone,check_in,check_out,total_guests,status,payment_status',
            'filters' => [Pg::eqStr('check_in', $today), 'status=in.(pending,confirmed,checkin)'],
            'order' => 'check_in.asc',
            'limit' => 200,
        ]);
        $departures = $this->db->select('bookings', [
            'select' => 'id,booking_reference,unit_id,guest_name,guest_email,guest_phone,check_in,check_out,total_guests,status,payment_status',
            'filters' => [Pg::eqStr('check_out', $today), 'status=in.(checkin,checkout)'],
            'order' => 'check_out.asc',
            'limit' => 200,
        ]);
        $occupied = $this->db->select('bookings', [
            'select' => 'id,unit_id',
            'filters' => ['status=eq.checkin', 'check_in=lte.' . $today, 'check_out=gt.' . $today],
            'limit' => 500,
        ]);
        $activeUnits = $this->countOf('units', 'status=eq.active');
        $occupiedUnits = count(array_unique(array_map('intval', array_column($occupied['ok'] ? ($occupied['data'] ?? []) : [], 'unit_id'))));

        return [
            'success' => true,
            'data' => [
                'date' => $today,
                'arrivals' => $this->enrichList($arrivals['ok'] ? ($arrivals['data'] ?? []) : []),
                'departures' => $this->enrichList($departures['ok'] ? ($departures['data'] ?? []) : []),
                'pending_bookings' => $this->countOf('bookings', 'status=eq.pending'),
                'confirmed_bookings' => $this->countOf('bookings', 'status=eq.confirmed'),
                'occupied_units' => $occupiedUnits,
                'available_units' => max(0, $activeUnits - $occupiedUnits),
                'active_units' => $activeUnits,
            ],
            'status' => 200,
        ];
    }

    public function revenueReport(): array
    {
        // 12 tháng gần nhất, chỉ paid
        $out = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        for ($i = 11; $i >= 0; $i--) {
            $month = $now->modify("first day of -{$i} month");
            $start = $month->format('Y-m-d');
            $end = $month->modify('+1 month')->format('Y-m-d');
            $res = $this->db->select('bookings', [
                'select'  => 'total_amount',
                'filters' => [Pg::eqStr('payment_status', 'paid'), 'created_at=gte.' . $start, 'created_at=lt.' . $end],
                'limit'   => 1000,
            ]);
            $total = 0;
            foreach (($res['ok'] ? $res['data'] : []) as $b) {
                $total += (int)($b['total_amount'] ?? 0);
            }
            $out[] = ['month' => $month->format('Y-m'), 'label' => $month->format('m/Y'), 'revenue' => $total];
        }

        $res = $this->db->select('bookings', [
            'select'  => 'payment_status,total_amount',
            'filters' => Pg::eqStr('payment_status', 'paid'),
            'limit'   => 5000,
        ]);
        $paidTotal = 0;
        $paidCount = 0;
        foreach (($res['ok'] ? $res['data'] : []) as $b) {
            $paidTotal += (int)($b['total_amount'] ?? 0);
            $paidCount++;
        }

        return [
            'success' => true,
            'data' => [
                'monthly' => $out,
                'paid_total' => $paidTotal,
                'paid_bookings' => $paidCount,
            ],
            'status' => 200,
        ];
    }
}
