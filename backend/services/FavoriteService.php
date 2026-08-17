<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * FAVORITE SERVICE — yêu thích (customer)
 *
 * Unique (customer_id, unit_id) — thêm trùng trả về "đã có trong danh sách".
 * ═════════════════════════════════════════════════════════════════════════
 */
final class FavoriteService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    public function list(int $customerId): array
    {
        $res = $this->db->select('favorites', [
            'select'  => 'id,unit_id,created_at',
            'filters' => Pg::eqInt('customer_id', $customerId),
            'order'   => 'created_at.desc',
            'limit'   => 100,
        ]);
        $favorites = $res['ok'] ? $res['data'] : [];

        $unitIds = array_map('intval', array_column($favorites, 'unit_id'));
        $unitMap = [];
        if ($unitIds) {
            $ur = $this->db->select('units', [
                'select'  => 'id,name,slug,type,base_price,cleaning_fee,max_guests,bedrooms,beds,bathrooms,area_m2,short_description,property_id,featured',
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
            $imgMap = [];
            $ir = $this->db->select('unit_images', [
                'select'  => 'unit_id,image_url,caption,is_cover,display_order',
                'filters' => Pg::inInts('unit_id', $unitIds),
                'order'   => 'is_cover.desc,display_order.asc',
                'limit'   => 300,
            ]);
            foreach (($ir['ok'] ? $ir['data'] : []) as $img) {
                if (!isset($imgMap[(int)$img['unit_id']])) {
                    $imgMap[(int)$img['unit_id']] = $img;
                }
            }
            foreach ($units as $u) {
                $u['property'] = $propMap[(int)$u['property_id']] ?? null;
                $u['cover_image'] = $imgMap[(int)$u['id']] ?? null;
                $unitMap[(int)$u['id']] = $u;
            }
        }

        foreach ($favorites as &$fav) {
            $fav['unit'] = $unitMap[(int)$fav['unit_id']] ?? null;
        }

        return ['success' => true, 'data' => $favorites, 'status' => 200];
    }

    public function add(int $customerId, int $unitId): array
    {
        // unit tồn tại?
        $res = $this->db->select('units', [
            'select'  => 'id',
            'filters' => [Pg::eqInt('id', $unitId), Pg::eqStr('status', 'active')],
            'limit'   => 1,
        ]);
        if (!$res['ok'] || !is_array($res['data']) || count($res['data']) === 0) {
            return ['success' => false, 'message' => 'Không tìm thấy căn homestay', 'status' => 404];
        }

        $ins = $this->db->insert('favorites', ['customer_id' => $customerId, 'unit_id' => $unitId]);
        if (!$ins['ok']) {
            // 23505 = đã có
            if (is_array($ins['data'] ?? null) && ($ins['data']['code'] ?? null) === '23505') {
                return ['success' => true, 'message' => 'Đã có trong danh sách yêu thích', 'status' => 200];
            }
            Security::logError('favorite', 'add_failed', ['status' => $ins['status']]);
            return ['success' => false, 'message' => 'Lỗi khi thêm yêu thích', 'status' => 503];
        }

        return ['success' => true, 'message' => 'Đã thêm vào yêu thích', 'status' => 201];
    }

    public function remove(int $customerId, int $unitId): array
    {
        $res = $this->db->delete('favorites', Pg::eqInt('customer_id', $customerId) . '&' . Pg::eqInt('unit_id', $unitId));
        if (!$res['ok']) {
            Security::logError('favorite', 'remove_failed', ['status' => $res['status']]);
            return ['success' => false, 'message' => 'Lỗi khi xóa yêu thích', 'status' => 503];
        }
        return ['success' => true, 'message' => 'Đã xóa khỏi yêu thích', 'status' => 200];
    }
}
