<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * ADMIN CATALOG SERVICE — properties / units / rate_rules / promotions CRUD
 *
 * • Property/Unit có booking → KHÔNG hard-delete, dùng status inactive/maintenance.
 * • Mọi thay đổi quan trọng ghi audit_logs.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class AdminCatalogService
{
    private SupabaseClient $db;

    private const UNIT_TYPES = ['studio', 'apartment', 'loft', 'couple', 'family', 'villa', 'house'];
    private const STATUSES = ['active', 'inactive', 'maintenance'];

    public function __construct()
    {
        $this->db = supabase();
    }

    /* ────────────────────────────────────────────────────────────────
     * PROPERTIES
     * ──────────────────────────────────────────────────────────────── */
    public function propertyList(int $page = 1, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $page = max(1, $page);
        $res = $this->db->select('properties', [
            'select'  => '*',
            'order'   => 'created_at.desc',
            'count'   => true,
            'limit'   => $limit,
            'offset'  => ($page - 1) * $limit,
        ]);
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi tải danh sách', 'status' => 503];
        }
        return [
            'success' => true,
            'data' => [
                'items' => $res['data'] ?? [],
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $res['count'] ?? 0, 'total_pages' => (int)ceil(($res['count'] ?? 0) / $limit)],
            ],
            'status' => 200,
        ];
    }

    public function propertyGet(int $id): array
    {
        $res = $this->db->select('properties', ['select' => '*', 'filters' => Pg::eqInt('id', $id), 'limit' => 1]);
        $p = ($res['ok'] && is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        return $p !== null
            ? ['success' => true, 'data' => $p, 'status' => 200]
            : ['success' => false, 'message' => 'Không tìm thấy', 'status' => 404];
    }

    public function propertySave(array $data, int $adminId, bool $isUpdate): array
    {
        try {
            $name    = Validation::string('name', $data['name'] ?? '', 150, true, 'tên homestay');
            $district = Validation::string('district', $data['district'] ?? '', 100, false, 'quận');
            $ward     = Validation::string('ward', $data['ward'] ?? '', 100, false, 'phường');
            $address  = Validation::string('address', $data['address'] ?? '', 255, false, 'địa chỉ');
            $code     = Validation::string('code', $data['code'] ?? '', 20, true, 'mã');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $status = Pg::whitelist($data['status'] ?? 'active', self::STATUSES, 'active');
        $slug = self::slugify($name);

        $payload = [
            'code'              => strtoupper($code),
            'name'              => $name,
            'slug'              => $slug,
            'district'          => $district !== '' ? $district : null,
            'ward'              => $ward !== '' ? $ward : null,
            'address'           => $address !== '' ? $address : null,
            'latitude'          => isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null,
            'longitude'         => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null,
            'short_description' => Security::limitInput($data['short_description'] ?? '', 255),
            'description'       => Security::limitInput($data['description'] ?? '', 10000),
            'story'             => Security::limitInput($data['story'] ?? '', 10000),
            'checkin_time'      => $data['checkin_time'] ?? '14:00:00',
            'checkout_time'     => $data['checkout_time'] ?? '12:00:00',
            'status'            => $status,
            'featured'          => !empty($data['featured']),
        ];

        if ($isUpdate) {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                return ['success' => false, 'message' => 'Thiếu id', 'status' => 422];
            }
            $res = $this->db->update('properties', $payload, Pg::eqInt('id', $id));
            if (!$res['ok']) {
                Security::logError('catalog', 'property_update_failed', ['status' => $res['status']]);
                return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
            }
            $this->audit($adminId, 'UPDATE_PROPERTY', 'properties', $id, ['name' => $name]);
            return ['success' => true, 'message' => 'Đã cập nhật homestay', 'data' => ['id' => $id], 'status' => 200];
        }

        $res = $this->db->insert('properties', $payload, ['select' => 'id']);
        if (!$res['ok']) {
            Security::logError('catalog', 'property_create_failed', ['status' => $res['status'], 'code' => $res['data']['code'] ?? null]);
            return ['success' => false, 'message' => 'Lỗi tạo (có thể trùng mã hoặc slug)', 'status' => 409];
        }
        $id = $res['data'][0]['id'] ?? null;
        $this->audit($adminId, 'CREATE_PROPERTY', 'properties', (int)$id, [], ['name' => $name]);
        return ['success' => true, 'message' => 'Đã tạo homestay', 'data' => ['id' => $id], 'status' => 201];
    }

    /** Deactivate (soft) — không xóa cứng property có booking. */
    public function propertySetStatus(int $id, string $status, int $adminId): array
    {
        $status = Pg::whitelist($status, self::STATUSES);
        if ($status === null) {
            return ['success' => false, 'message' => 'Trạng thái không hợp lệ.', 'status' => 422];
        }
        $res = $this->db->update('properties', ['status' => $status], Pg::eqInt('id', $id));
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
        }
        $this->audit($adminId, 'UPDATE_PROPERTY', 'properties', $id, [], ['status' => $status]);
        return ['success' => true, 'message' => 'Đã cập nhật trạng thái', 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * UNITS
     * ──────────────────────────────────────────────────────────────── */
    public function unitList(int $propertyId = 0, int $page = 1, int $limit = 50): array
    {
        $filters = [];
        if ($propertyId > 0) {
            $filters[] = Pg::eqInt('property_id', $propertyId);
        }
        $res = $this->db->select('units', [
            'select'  => '*',
            'filters' => $filters,
            'order'   => 'property_id.asc,id.asc',
            'limit'   => $limit,
        ]);
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi tải danh sách', 'status' => 503];
        }
        return ['success' => true, 'data' => ['items' => $res['data'] ?? []], 'status' => 200];
    }

    public function unitGet(int $id): array
    {
        $res = $this->db->select('units', ['select' => '*', 'filters' => Pg::eqInt('id', $id), 'limit' => 1]);
        $u = ($res['ok'] && is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($u === null) {
            return ['success' => false, 'message' => 'Không tìm thấy căn', 'status' => 404];
        }

        $join = $this->db->select('unit_amenities', ['select' => 'amenity_id', 'filters' => Pg::eqInt('unit_id', $id), 'limit' => 50]);
        $u['amenity_ids'] = array_map('intval', array_column(($join['ok'] ? $join['data'] : []), 'amenity_id'));

        $tj = $this->db->select('unit_experience_tags', ['select' => 'tag_id,weight', 'filters' => Pg::eqInt('unit_id', $id), 'limit' => 50]);
        $u['tags'] = ($tj['ok'] ? $tj['data'] : []);

        $imgs = $this->db->select('unit_images', ['select' => 'id,storage_path,image_url,caption,image_category,display_order,is_cover', 'filters' => Pg::eqInt('unit_id', $id), 'order' => 'display_order.asc', 'limit' => 50]);
        $u['images'] = $imgs['ok'] ? $imgs['data'] : [];

        return ['success' => true, 'data' => $u, 'status' => 200];
    }

    public function unitSave(array $data, int $adminId, bool $isUpdate): array
    {
        try {
            $propertyId = Validation::nonNegativeInt('property_id', $data['property_id'] ?? null, 'homestay');
            $name       = Validation::string('name', $data['name'] ?? '', 150, true, 'tên căn');
            $code       = Validation::string('code', $data['code'] ?? '', 20, true, 'mã căn');
            $basePrice  = Validation::nonNegativeInt('base_price', $data['base_price'] ?? null, 'giá đêm');
            $cleaning   = Validation::nonNegativeInt('cleaning_fee', $data['cleaning_fee'] ?? 0, 'phí vệ sinh');
            $maxGuests  = Validation::intInRange('max_guests', $data['max_guests'] ?? 2, 1, 20, 'số khách');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $type = Pg::whitelist($data['type'] ?? 'studio', self::UNIT_TYPES, 'studio');
        $status = Pg::whitelist($data['status'] ?? 'active', self::STATUSES, 'active');

        $payload = [
            'property_id'       => $propertyId,
            'code'              => $code,
            'name'              => $name,
            'slug'              => self::slugify($name),
            'type'              => $type,
            'base_price'        => $basePrice,
            'cleaning_fee'      => $cleaning,
            'max_guests'        => $maxGuests,
            'bedrooms'          => (int)($data['bedrooms'] ?? 1),
            'beds'              => (int)($data['beds'] ?? 1),
            'bathrooms'         => (int)($data['bathrooms'] ?? 1),
            'area_m2'           => isset($data['area_m2']) && $data['area_m2'] !== '' ? (int)$data['area_m2'] : null,
            'short_description' => Security::limitInput($data['short_description'] ?? '', 255),
            'description'       => Security::limitInput($data['description'] ?? '', 10000),
            'house_rules'       => Security::limitInput($data['house_rules'] ?? '', 5000),
            'status'            => $status,
            'featured'          => !empty($data['featured']),
        ];

        if ($isUpdate) {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                return ['success' => false, 'message' => 'Thiếu id', 'status' => 422];
            }
            $res = $this->db->update('units', $payload, Pg::eqInt('id', $id));
            if (!$res['ok']) {
                Security::logError('catalog', 'unit_update_failed', ['status' => $res['status']]);
                return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
            }
        } else {
            $res = $this->db->insert('units', $payload, ['select' => 'id']);
            if (!$res['ok']) {
                Security::logError('catalog', 'unit_create_failed', ['status' => $res['status'], 'code' => $res['data']['code'] ?? null]);
                return ['success' => false, 'message' => 'Lỗi tạo (có thể trùng mã)', 'status' => 409];
            }
            $id = (int)($res['data'][0]['id'] ?? 0);
        }

        // amenities + tags
        $this->saveUnitAmenities($id, $data['amenity_ids'] ?? []);
        $this->saveUnitTags($id, $data['tags'] ?? []);
        // images
        if (isset($data['images']) && is_array($data['images'])) {
            $this->saveUnitImages($id, $data['images']);
        }

        $this->audit($adminId, $isUpdate ? 'UPDATE_UNIT' : 'CREATE_UNIT', 'units', $id, [], ['name' => $name, 'base_price' => $basePrice]);

        return ['success' => true, 'message' => $isUpdate ? 'Đã cập nhật căn homestay' : 'Đã tạo căn homestay', 'data' => ['id' => $id], 'status' => $isUpdate ? 200 : 201];
    }

    public function unitSetStatus(int $id, string $status, int $adminId): array
    {
        $status = Pg::whitelist($status, self::STATUSES);
        if ($status === null) {
            return ['success' => false, 'message' => 'Trạng thái không hợp lệ.', 'status' => 422];
        }
        $res = $this->db->update('units', ['status' => $status], Pg::eqInt('id', $id));
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
        }
        $this->audit($adminId, 'UPDATE_UNIT', 'units', $id, [], ['status' => $status]);
        return ['success' => true, 'message' => 'Đã cập nhật trạng thái', 'status' => 200];
    }

    private function saveUnitAmenities(int $unitId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($n) => $n > 0));
        $this->db->delete('unit_amenities', Pg::eqInt('unit_id', $unitId));
        foreach ($ids as $aid) {
            $this->db->insert('unit_amenities', ['unit_id' => $unitId, 'amenity_id' => $aid]);
        }
    }

    private function saveUnitTags(int $unitId, array $tags): void
    {
        $this->db->delete('unit_experience_tags', Pg::eqInt('unit_id', $unitId));
        foreach ($tags as $t) {
            $tagId = (int)($t['tag_id'] ?? $t['id'] ?? 0);
            $weight = (int)($t['weight'] ?? 5);
            if ($tagId > 0) {
                $this->db->insert('unit_experience_tags', ['unit_id' => $unitId, 'tag_id' => $tagId, 'weight' => max(1, min(10, $weight))]);
            }
        }
    }

    private function saveUnitImages(int $unitId, array $images): void
    {
        $this->db->delete('unit_images', Pg::eqInt('unit_id', $unitId));
        $order = 0;
        foreach ($images as $img) {
            $url = trim((string)($img['image_url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $this->db->insert('unit_images', [
                'unit_id'       => $unitId,
                'storage_path'  => isset($img['storage_path']) && $img['storage_path'] !== '' ? $img['storage_path'] : null,
                'image_url'     => $url,
                'caption'       => Security::limitInput($img['caption'] ?? '', 255),
                'image_category'=> Pg::whitelist($img['image_category'] ?? 'other', ['bedroom', 'bathroom', 'kitchen', 'balcony', 'view', 'workspace', 'living_room', 'building', 'other'], 'other'),
                'display_order' => $order++,
                'is_cover'      => !empty($img['is_cover']),
            ]);
        }
    }

    /* ────────────────────────────────────────────────────────────────
     * RATE RULES
     * ──────────────────────────────────────────────────────────────── */
    public function rateRuleList(?int $unitId = null): array
    {
        $filters = [];
        if ($unitId !== null && $unitId > 0) {
            $filters[] = Pg::eqInt('unit_id', $unitId);
        }
        $res = $this->db->select('rate_rules', [
            'select'  => '*',
            'filters' => $filters,
            'order'   => 'priority.desc,id.desc',
            'limit'   => 200,
        ]);
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi tải luật giá', 'status' => 503];
        }
        // tên unit cho hiển thị
        $units = $this->db->select('units', ['select' => 'id,name', 'limit' => 200]);
        $unitMap = [];
        foreach (($units['ok'] ? $units['data'] : []) as $u) {
            $unitMap[(int)$u['id']] = $u['name'];
        }
        $items = $res['data'];
        foreach ($items as &$r) {
            $r['unit_name'] = $unitMap[(int)$r['unit_id']] ?? null;
        }
        return ['success' => true, 'data' => ['items' => $items], 'status' => 200];
    }

    public function rateRuleSave(array $data, int $adminId, bool $isUpdate): array
    {
        try {
            $unitId = Validation::nonNegativeInt('unit_id', $data['unit_id'] ?? null, 'căn');
            $name   = Validation::string('name', $data['name'] ?? '', 150, true, 'tên luật');
            $price  = Validation::nonNegativeInt('price', $data['price'] ?? null, 'giá');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $dayOfWeek = ($data['day_of_week'] ?? null) !== null && $data['day_of_week'] !== ''
            ? (int)$data['day_of_week'] : null;
        if ($dayOfWeek !== null && ($dayOfWeek < 1 || $dayOfWeek > 7)) {
            return ['success' => false, 'message' => 'day_of_week phải từ 1 (CN) đến 7 (T7).', 'status' => 422];
        }
        $start = ($data['start_date'] ?? null) !== '' ? ($data['start_date'] ?? null) : null;
        $end   = ($data['end_date'] ?? null) !== '' ? ($data['end_date'] ?? null) : null;
        if ($start !== null && $end !== null && $end <= $start) {
            return ['success' => false, 'message' => 'end_date phải sau start_date (end exclusive).', 'status' => 422];
        }

        $payload = [
            'unit_id'     => $unitId,
            'name'        => $name,
            'start_date'  => $start,
            'end_date'    => $end,
            'day_of_week' => $dayOfWeek,
            'price'       => $price,
            'priority'    => (int)($data['priority'] ?? 0),
            'active'      => !empty($data['active']),
        ];

        if ($isUpdate) {
            $id = (int)($data['id'] ?? 0);
            $res = $this->db->update('rate_rules', $payload, Pg::eqInt('id', $id));
            if (!$res['ok']) {
                return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
            }
        } else {
            $res = $this->db->insert('rate_rules', $payload, ['select' => 'id']);
            if (!$res['ok']) {
                return ['success' => false, 'message' => 'Lỗi tạo', 'status' => 503];
            }
            $id = (int)($res['data'][0]['id'] ?? 0);
        }

        $this->audit($adminId, 'UPDATE_RATE_RULE', 'rate_rules', (int)$id, [], ['name' => $name, 'price' => $price]);
        return ['success' => true, 'message' => 'Đã lưu luật giá', 'data' => ['id' => $id], 'status' => 200];
    }

    public function rateRuleDelete(int $id, int $adminId): array
    {
        $this->db->delete('rate_rules', Pg::eqInt('id', $id));
        $this->audit($adminId, 'DELETE_RATE_RULE', 'rate_rules', $id, [], []);
        return ['success' => true, 'message' => 'Đã xóa luật giá', 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * PROMOTIONS
     * ──────────────────────────────────────────────────────────────── */
    public function promotionList(): array
    {
        $res = $this->db->select('promotions', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 200]);
        return ['success' => true, 'data' => ['items' => $res['ok'] ? $res['data'] : []], 'status' => 200];
    }

    public function promotionSave(array $data, int $adminId, bool $isUpdate): array
    {
        try {
            $code   = Validation::string('code', $data['code'] ?? '', 40, true, 'mã');
            $name   = Validation::string('name', $data['name'] ?? '', 150, true, 'tên');
            $value  = Validation::nonNegativeInt('discount_value', $data['discount_value'] ?? null, 'giá trị giảm');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $type = Pg::whitelist($data['discount_type'] ?? 'percentage', ['percentage', 'fixed_amount'], 'percentage');
        $validFrom = (string)($data['valid_from'] ?? date('Y-m-d'));
        $validUntil = (string)($data['valid_until'] ?? '2099-12-31');
        if ($validUntil < $validFrom) {
            return ['success' => false, 'message' => 'valid_until phải sau valid_from.', 'status' => 422];
        }

        $payload = [
            'code'                     => strtoupper($code),
            'name'                     => $name,
            'description'              => Security::limitInput($data['description'] ?? '', 500),
            'discount_type'            => $type,
            'discount_value'           => $value,
            'minimum_booking_value'    => isset($data['minimum_booking_value']) && $data['minimum_booking_value'] !== '' ? (int)$data['minimum_booking_value'] : null,
            'maximum_discount'         => isset($data['maximum_discount']) && $data['maximum_discount'] !== '' ? (int)$data['maximum_discount'] : null,
            'valid_from'               => $validFrom,
            'valid_until'              => $validUntil,
            'usage_limit'              => isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null,
            'usage_limit_per_customer' => isset($data['usage_limit_per_customer']) && $data['usage_limit_per_customer'] !== '' ? (int)$data['usage_limit_per_customer'] : null,
            'active'                   => !empty($data['active']),
        ];

        if ($isUpdate) {
            $id = (int)($data['id'] ?? 0);
            $res = $this->db->update('promotions', $payload, Pg::eqInt('id', $id));
            if (!$res['ok']) {
                return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
            }
        } else {
            $res = $this->db->insert('promotions', $payload, ['select' => 'id']);
            if (!$res['ok']) {
                if (is_array($res['data'] ?? null) && ($res['data']['code'] ?? null) === '23505') {
                    return ['success' => false, 'message' => 'Mã ưu đãi đã tồn tại.', 'status' => 409];
                }
                return ['success' => false, 'message' => 'Lỗi tạo mã', 'status' => 503];
            }
            $id = (int)($res['data'][0]['id'] ?? 0);
        }

        $this->audit($adminId, 'UPDATE_PROMOTION', 'promotions', (int)$id, [], ['code' => $code]);
        return ['success' => true, 'message' => 'Đã lưu mã ưu đãi', 'data' => ['id' => $id], 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * HELPERS
     * ──────────────────────────────────────────────────────────────── */
    private function audit(int $adminId, string $action, string $entityType, int $entityId, array $old, array $new): void
    {
        (new AdminBookingService())->audit($adminId, $action, $entityType, $entityId, $old, $new);
    }

    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $map = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'đ'=>'d','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim((string)$text, '-');
    }
}
