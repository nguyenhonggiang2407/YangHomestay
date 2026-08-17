<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * ADMIN CUSTOMER SERVICE — danh sách khách hàng cho admin
 *
 * Hiển thị: name, email, phone, joined, booking count, total spend, status.
 * Search: name | email | phone.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class AdminCustomerService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    public function list(array $f, int $page = 1, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $page  = max(1, $page);

        $filters = [];
        $status = Pg::whitelist($f['status'] ?? null, ['active', 'inactive', 'blocked']);
        if ($status !== null) {
            $filters[] = Pg::eqStr('status', $status);
        }

        $res = $this->db->select('customers', [
            'select'  => 'id,full_name,email,phone,avatar_url,status,created_at,last_login_at',
            'filters' => $filters,
            'order'   => 'created_at.desc',
            'count'   => true,
            'limit'   => $limit,
            'offset'  => ($page - 1) * $limit,
        ]);

        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi tải danh sách khách hàng', 'status' => 503];
        }
        $items = $res['data'] ?? [];
        $total = $res['count'] ?? count($items);

        // search (trên trang hiện tại — dataset nhỏ)
        if (!empty($f['search'])) {
            $q = mb_strtolower(trim($f['search']));
            $items = array_values(array_filter($items, function ($c) use ($q) {
                $hay = mb_strtolower(($c['full_name'] ?? '') . ' ' . ($c['email'] ?? '') . ' ' . ($c['phone'] ?? ''));
                return str_contains($hay, $q);
            }));
            $total = count($items);
        }

        // booking stats per customer
        $items = $this->withBookingStats($items);

        return [
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int)ceil($total / $limit)],
            ],
            'status' => 200,
        ];
    }

    private function withBookingStats(array $customers): array
    {
        if (!$customers) {
            return $customers;
        }
        $ids = array_map('intval', array_column($customers, 'id'));
        $res = $this->db->select('bookings', [
            'select'  => 'customer_id,total_amount,status,payment_status',
            'filters' => Pg::inInts('customer_id', $ids),
            'limit'   => 2000,
        ]);
        $bookings = $res['ok'] ? $res['data'] : [];

        $countMap = [];
        $spendMap = [];
        foreach ($bookings as $b) {
            $cid = (int)$b['customer_id'];
            $countMap[$cid] = ($countMap[$cid] ?? 0) + 1;
            if (in_array($b['status'] ?? '', ['checkin', 'checkout'], true) && ($b['payment_status'] ?? '') === 'paid') {
                $spendMap[$cid] = ($spendMap[$cid] ?? 0) + (int)$b['total_amount'];
            }
        }

        foreach ($customers as &$c) {
            $cid = (int)$c['id'];
            $c['booking_count'] = $countMap[$cid] ?? 0;
            $c['total_spend']   = $spendMap[$cid] ?? 0;
        }
        return $customers;
    }
}
