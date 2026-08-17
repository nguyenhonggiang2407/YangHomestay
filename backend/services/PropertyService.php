<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * PROPERTY SERVICE — danh sách + chi tiết property (public)
 *
 * Public CHỈ trả properties.status = 'active' (backend luôn filter).
 * Dataset nhỏ (≤50) → filter/sort/paginate trong PHP sau khi lấy 1 lần
 * từ Supabase (tránh lỗ hổng inject filter syntax; dễ test).
 * ═════════════════════════════════════════════════════════════════════════
 */
final class PropertyService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    /**
     * @param array $f filters: district, featured, search, sort
     */
    public function list(array $f, int $page = 1, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $page  = max(1, $page);

        $res = $this->db->select('properties', [
            'select'  => 'id,code,name,slug,district,ward,address,latitude,longitude,short_description,description,story,checkin_time,checkout_time,status,featured,created_at',
            'filters' => Pg::eqStr('status', 'active'),
            'limit'   => 50,
        ]);

        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi tải danh sách homestay', 'status' => 503];
        }

        $items = is_array($res['data']) ? $res['data'] : [];

        // Filter trong PHP
        if (!empty($f['district'])) {
            $items = array_values(array_filter($items, fn($p) => strcasecmp((string)($p['district'] ?? ''), $f['district']) === 0));
        }
        if (!empty($f['featured']) && $f['featured'] !== 'false' && $f['featured'] !== '0') {
            $items = array_values(array_filter($items, fn($p) => !empty($p['featured'])));
        }
        if (!empty($f['search'])) {
            $q = mb_strtolower(trim($f['search']));
            $items = array_values(array_filter($items, function ($p) use ($q) {
                $hay = mb_strtolower(($p['name'] ?? '') . ' ' . ($p['district'] ?? '') . ' ' . ($p['slug'] ?? ''));
                return str_contains($hay, $q);
            }));
        }

        // Sort
        $sort = (string)($f['sort'] ?? 'featured');
        usort($items, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'name_asc':
                    return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                case 'name_desc':
                    return strcmp((string)($b['name'] ?? ''), (string)($a['name'] ?? ''));
                case 'newest':
                    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
                default: // featured first, then newest
                    $fa = !empty($a['featured']) ? 0 : 1;
                    $fb = !empty($b['featured']) ? 0 : 1;
                    if ($fa !== $fb) return $fa <=> $fb;
                    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
            }
        });

        $total = count($items);
        $items = array_slice($items, ($page - 1) * $limit, $limit);

        // Enrich: cover image + unit count
        $items = $this->enrich($items);

        return [
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int)ceil($total / $limit)],
            ],
            'status' => 200,
        ];
    }

    private function enrich(array $items): array
    {
        if (!$items) {
            return $items;
        }
        $ids = array_column($items, 'id');

        $images = $this->db->select('property_images', [
            'select'  => 'property_id,image_url,caption,image_category,display_order,is_cover',
            'filters' => Pg::inInts('property_id', $ids),
            'limit'   => 100,
        ]);
        $imgMap = [];
        foreach (($images['ok'] ? $images['data'] : []) as $img) {
            $imgMap[(int)$img['property_id']][] = $img;
        }

        $counts = $this->db->select('units', [
            'select'  => 'property_id',
            'filters' => [Pg::inInts('property_id', $ids), Pg::eqStr('status', 'active')],
            'limit'   => 100,
        ]);
        $countMap = [];
        foreach (($counts['ok'] ? $counts['data'] : []) as $u) {
            $countMap[(int)$u['property_id']] = ($countMap[(int)$u['property_id']] ?? 0) + 1;
        }

        foreach ($items as &$p) {
            $gallery = $imgMap[(int)$p['id']] ?? [];
            $cover = null;
            foreach ($gallery as $img) {
                if (!empty($img['is_cover'])) { $cover = $img; break; }
            }
            $p['cover_image'] = $cover ?: ($gallery[0] ?? null);
            $p['gallery'] = $gallery;
            $p['unit_count'] = $countMap[(int)$p['id']] ?? 0;
        }
        return $items;
    }

    public function detail(int $id): array
    {
        $res = $this->db->select('properties', [
            'select'  => '*',
            'filters' => [Pg::eqInt('id', $id), Pg::eqStr('status', 'active')],
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('properties', 'detail_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải homestay lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $prop = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($prop === null) {
            return ['success' => false, 'message' => 'Không tìm thấy homestay', 'status' => 404];
        }

        $images = $this->db->select('property_images', [
            'select'  => 'image_url,caption,image_category,display_order,is_cover',
            'filters' => Pg::eqInt('property_id', $id),
            'order'   => 'display_order.asc',
            'limit'   => 50,
        ]);
        if (!$images['ok']) {
            Security::logError('properties', 'detail_gallery_failed', ['status' => $images['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải gallery homestay lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $prop['gallery'] = is_array($images['data']) ? $images['data'] : [];

        $nearby = $this->db->select('nearby_places', [
            'select'  => 'id,name,category,short_description,distance_meters,walking_minutes,image_url,featured',
            'filters' => Pg::eqInt('property_id', $id),
            'order'   => 'distance_meters.asc',
            'limit'   => 50,
        ]);
        if (!$nearby['ok']) {
            Security::logError('properties', 'detail_nearby_failed', ['status' => $nearby['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải thông tin khu vực lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $prop['nearby_places'] = is_array($nearby['data']) ? $nearby['data'] : [];

        // units active của property (kèm rating + cover)
        $unitService = new UnitService();
        $units = $unitService->listByProperty($id);
        if ($unitService->lastError() !== null) {
            return ['success' => false, 'message' => $unitService->lastError(), 'status' => 503];
        }

        return [
            'success' => true,
            'data' => [
                'property' => $prop,
                'units'    => $units,
            ],
            'status' => 200,
        ];
    }
}
