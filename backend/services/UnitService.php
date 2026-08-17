<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * UNIT SERVICE — danh sách + chi tiết unit (public)
 *
 * Public CHỈ trả units.status='active' VÀ property.status='active'.
 * Filter/sort/paginate trong PHP trên tập ≤50 (dataset nhỏ, an toàn).
 * Sort whitelist: price_asc | price_desc | newest | rating.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class UnitService
{
    private SupabaseClient $db;
    private ?string $dataError = null;

    public const SORTS = ['price_asc', 'price_desc', 'newest', 'rating'];
    public const TYPES = ['studio', 'apartment', 'loft', 'couple', 'family', 'villa', 'house'];

    public function __construct()
    {
        $this->db = supabase();
    }

    /**
     * @param array $f filters: property, type, min_price, max_price, guests,
     *                  check_in, check_out, district, featured, search, sort
     */
    public function list(array $f, int $page = 1, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $page  = max(1, $page);

        $checkIn = trim((string)($f['check_in'] ?? ''));
        $checkOut = trim((string)($f['check_out'] ?? ''));
        if (($checkIn === '') xor ($checkOut === '')) {
            return ['success' => false, 'message' => 'Vui lòng chọn cả ngày nhận và ngày trả phòng.', 'status' => 422];
        }
        if ($checkIn !== '') {
            $dateError = AvailabilityService::validateDates($checkIn, $checkOut);
            if ($dateError !== null) {
                return ['success' => false, 'message' => $dateError, 'status' => 422];
            }
        }

        $minPrice = ($f['min_price'] ?? '') !== '' ? (int)$f['min_price'] : null;
        $maxPrice = ($f['max_price'] ?? '') !== '' ? (int)$f['max_price'] : null;
        if ($minPrice !== null && $minPrice < 0 || $maxPrice !== null && $maxPrice < 0) {
            return ['success' => false, 'message' => 'Khoảng giá không hợp lệ.', 'status' => 422];
        }
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            return ['success' => false, 'message' => 'Giá tối thiểu không được lớn hơn giá tối đa.', 'status' => 422];
        }

        $guests = ($f['guests'] ?? '') !== '' ? (int)$f['guests'] : null;
        if ($guests !== null && ($guests < 1 || $guests > 20)) {
            return ['success' => false, 'message' => 'Số khách không hợp lệ.', 'status' => 422];
        }

        $items = $this->fetchActiveUnits();
        if ($this->dataError !== null) {
            return ['success' => false, 'message' => $this->dataError, 'status' => 503];
        }

        // Lọc availability theo ngày. Fail-closed: nếu RPC lỗi, không trả kết quả
        // giả vờ còn phòng vì điều này có thể dẫn tới quyết định đặt sai.
        if ($checkIn !== '') {
            $avail = $this->db->rpc('available_units', [
                'p_check_in'  => $checkIn,
                'p_check_out' => $checkOut,
            ]);
            if (!$avail['ok']) {
                Security::logError('units', 'availability_filter_failed', ['status' => $avail['status'] ?? 0]);
                return ['success' => false, 'message' => 'Chưa thể kiểm tra phòng trống lúc này. Vui lòng thử lại.', 'status' => 503];
            }
            $availableIds = [];
            foreach ((array)$avail['data'] as $row) {
                if (is_array($row) && isset($row['unit_id'])) {
                    $availableIds[] = (int)$row['unit_id'];
                }
            }
            $items = array_values(array_filter($items, fn($u) => in_array((int)$u['id'], $availableIds, true)));
        }

        if (!empty($f['property'])) {
            $items = array_values(array_filter($items, fn($u) => (int)$u['property_id'] === (int)$f['property']));
        }
        if (!empty($f['type']) && in_array($f['type'], self::TYPES, true)) {
            $items = array_values(array_filter($items, fn($u) => ($u['type'] ?? '') === $f['type']));
        }
        if ($minPrice !== null) {
            $items = array_values(array_filter($items, fn($u) => (int)$u['base_price'] >= $minPrice));
        }
        if ($maxPrice !== null) {
            $items = array_values(array_filter($items, fn($u) => (int)$u['base_price'] <= $maxPrice));
        }
        if ($guests !== null) {
            $items = array_values(array_filter($items, fn($u) => (int)$u['max_guests'] >= $guests));
        }
        if (!empty($f['district'])) {
            $items = array_values(array_filter($items, fn($u) => strcasecmp((string)($u['property']['district'] ?? ''), (string)$f['district']) === 0));
        }
        if (!empty($f['featured']) && $f['featured'] !== 'false' && $f['featured'] !== '0') {
            $items = array_values(array_filter($items, fn($u) => !empty($u['featured'])));
        }
        if (!empty($f['search'])) {
            $q = mb_strtolower(trim((string)$f['search']), 'UTF-8');
            $items = array_values(array_filter($items, function ($u) use ($q) {
                $hay = mb_strtolower(
                    ($u['name'] ?? '') . ' ' .
                    ($u['property']['name'] ?? '') . ' ' .
                    ($u['property']['district'] ?? '') . ' ' .
                    ($u['slug'] ?? ''),
                    'UTF-8'
                );
                return str_contains($hay, $q);
            }));
        }
        if (!empty($f['tag'])) {
            $tagMap = $this->tagMap();
            if ($this->dataError !== null) {
                return ['success' => false, 'message' => $this->dataError, 'status' => 503];
            }
            $tagCode = (string)$f['tag'];
            if (!isset($tagMap[$tagCode])) {
                // Tag URL không hợp lệ: không âm thầm bỏ filter và trả toàn bộ căn.
                return [
                    'success' => true,
                    'data' => [
                        'items' => [],
                        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'total_pages' => 0],
                    ],
                    'status' => 200,
                ];
            }
            $unitIds = $this->unitsWithTag($tagMap[$tagCode]);
            if ($this->dataError !== null) {
                return ['success' => false, 'message' => $this->dataError, 'status' => 503];
            }
            $items = array_values(array_filter($items, fn($u) => in_array((int)$u['id'], $unitIds, true)));
        }

        $sort = in_array($f['sort'] ?? '', self::SORTS, true) ? $f['sort'] : 'price_asc';
        $ratingMap = $sort === 'rating' ? $this->ratingMap() : [];
        usort($items, function ($a, $b) use ($sort, $ratingMap) {
            switch ($sort) {
                case 'price_desc': return (int)$b['base_price'] <=> (int)$a['base_price'];
                case 'newest': return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
                case 'rating':
                    $ra = (float)($ratingMap[(int)$a['id']]['avg_rating'] ?? 0);
                    $rb = (float)($ratingMap[(int)$b['id']]['avg_rating'] ?? 0);
                    return $rb <=> $ra;
                default: return (int)$a['base_price'] <=> (int)$b['base_price'];
            }
        });

        $total = count($items);
        $items = array_slice($items, ($page - 1) * $limit, $limit);

        return [
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int)ceil($total / $limit)],
            ],
            'status' => 200,
        ];
    }

    /** Toàn bộ unit active + property active (kèm property info + cover image + rating). */
    private function fetchActiveUnits(): array
    {
        $res = $this->db->select('units', [
            'select'  => 'id,property_id,code,name,slug,type,base_price,cleaning_fee,max_guests,bedrooms,beds,bathrooms,area_m2,short_description,description,house_rules,status,featured,created_at',
            'filters' => Pg::eqStr('status', 'active'),
            'limit'   => 50,
        ]);
        if (!$res['ok']) {
            Security::logError('units', 'list_units_failed', ['status' => $res['status'] ?? 0]);
            $this->dataError = 'Không thể tải danh sách homestay lúc này. Vui lòng thử lại.';
            return [];
        }
        $units = is_array($res['data']) ? $res['data'] : [];

        // property map (chỉ active)
        $propRes = $this->db->select('properties', [
            'select'  => 'id,name,slug,district,ward,status',
            'filters' => Pg::eqStr('status', 'active'),
            'limit'   => 50,
        ]);
        if (!$propRes['ok']) {
            Security::logError('units', 'list_properties_failed', ['status' => $propRes['status'] ?? 0]);
            $this->dataError = 'Không thể tải thông tin khu vực lúc này. Vui lòng thử lại.';
            return [];
        }
        $propMap = [];
        foreach ((is_array($propRes['data']) ? $propRes['data'] : []) as $p) {
            $propMap[(int)$p['id']] = $p;
        }

        $ratingMap = $this->ratingMap();

        foreach ($units as &$u) {
            $pid = (int)$u['property_id'];
            if (!isset($propMap[$pid])) {
                $u = null; // property không active → bỏ
                continue;
            }
            $u['property'] = $propMap[$pid];
            $r = $ratingMap[(int)$u['id']] ?? null;
            $u['avg_rating'] = $r ? (float)$r['avg_rating'] : 0.0;
            $u['review_count'] = $r ? (int)$r['review_count'] : 0;
        }
        unset($u);
        $units = array_values(array_filter($units, static fn($u) => is_array($u) && isset($u['id'])));

        // cover image (ưu tiên is_cover, rồi display_order)
        $ids = array_map('intval', array_column($units, 'id'));
        if ($ids) {
            $img = $this->db->select('unit_images', [
                'select'  => 'unit_id,image_url,caption,image_category,display_order,is_cover',
                'filters' => Pg::inInts('unit_id', $ids),
                'order'   => 'is_cover.desc,display_order.asc',
                'limit'   => 300,
            ]);
            if (!$img['ok']) {
                Security::logError('units', 'list_images_failed', ['status' => $img['status'] ?? 0]);
                $this->dataError = 'Không thể tải hình ảnh homestay lúc này. Vui lòng thử lại.';
                return [];
            }
            $imgMap = [];
            foreach ((is_array($img['data']) ? $img['data'] : []) as $i) {
                if (!isset($imgMap[(int)$i['unit_id']])) {
                    $imgMap[(int)$i['unit_id']] = $i;
                }
            }
            foreach ($units as &$u) {
                $u['cover_image'] = $imgMap[(int)$u['id']] ?? null;
            }
            unset($u);
        }
        return array_values(array_filter($units));
    }

    public function listByProperty(int $propertyId): array
    {
        $this->dataError = null;
        $items = array_values(array_filter($this->fetchActiveUnits(), fn($u) => (int)$u['property_id'] === $propertyId));
        if ($this->dataError !== null) {
            return [];
        }
        // cover image per unit
        $ids = array_column($items, 'id');
        if ($ids) {
            $img = $this->db->select('unit_images', [
                'select'  => 'unit_id,image_url,caption,image_category,display_order,is_cover',
                'filters' => Pg::inInts('unit_id', $ids),
                'limit'   => 200,
            ]);
            if (!$img['ok']) {
                Security::logError('units', 'property_gallery_failed', ['status' => $img['status'] ?? 0]);
                $this->dataError = 'Không thể tải các căn của homestay lúc này. Vui lòng thử lại.';
                return [];
            }
            $map = [];
            foreach ((is_array($img['data']) ? $img['data'] : []) as $i) {
                $map[(int)$i['unit_id']][] = $i;
            }
            foreach ($items as &$u) {
                $gallery = $map[(int)$u['id']] ?? [];
                $cover = null;
                foreach ($gallery as $i) {
                    if (!empty($i['is_cover'])) { $cover = $i; break; }
                }
                $u['cover_image'] = $cover ?: ($gallery[0] ?? null);
                $u['gallery'] = $gallery;
            }
        }
        return $items;
    }

    public function lastError(): ?string
    {
        return $this->dataError;
    }

    public function detail(int $id): array
    {
        $res = $this->db->select('units', [
            'select'  => '*',
            'filters' => [Pg::eqInt('id', $id), Pg::eqStr('status', 'active')],
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('units', 'detail_unit_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải căn homestay lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $unit = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($unit === null) {
            return ['success' => false, 'message' => 'Không tìm thấy căn homestay', 'status' => 404];
        }

        // property
        $propRes = $this->db->select('properties', [
            'select'  => 'id,code,name,slug,district,ward,address,latitude,longitude,short_description,description,story,checkin_time,checkout_time,status,featured',
            'filters' => [Pg::eqInt('id', (int)$unit['property_id']), Pg::eqStr('status', 'active')],
            'limit'   => 1,
        ]);
        if (!$propRes['ok']) {
            Security::logError('units', 'detail_property_failed', ['status' => $propRes['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải thông tin vị trí lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $prop = (is_array($propRes['data']) && isset($propRes['data'][0])) ? $propRes['data'][0] : null;
        if ($prop === null) {
            return ['success' => false, 'message' => 'Không tìm thấy căn homestay', 'status' => 404];
        }
        $unit['property'] = $prop;

        // images
        $img = $this->db->select('unit_images', [
            'select'  => 'image_url,caption,image_category,display_order,is_cover',
            'filters' => Pg::eqInt('unit_id', $id),
            'order'   => 'display_order.asc',
            'limit'   => 50,
        ]);
        if (!$img['ok']) {
            Security::logError('units', 'detail_gallery_failed', ['status' => $img['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải gallery lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $unit['gallery'] = is_array($img['data']) ? $img['data'] : [];
        $cover = null;
        foreach ($unit['gallery'] as $g) { if (!empty($g['is_cover'])) { $cover = $g; break; } }
        $unit['cover_image'] = $cover ?: ($unit['gallery'][0] ?? null);

        // amenities
        $unit['amenities'] = $this->amenitiesForUnit($id);
        if ($this->dataError !== null) {
            return ['success' => false, 'message' => $this->dataError, 'status' => 503];
        }

        // experience tags
        $unit['experience_tags'] = $this->tagsForUnit($id);
        if ($this->dataError !== null) {
            return ['success' => false, 'message' => $this->dataError, 'status' => 503];
        }

        // rating
        $r = $this->ratingMap();
        $unit['avg_rating'] = (float)($r[$id]['avg_rating'] ?? 0);
        $unit['review_count'] = (int)($r[$id]['review_count'] ?? 0);

        // reviews approved
        $unit['reviews'] = $this->reviewsForUnit($id);
        if ($this->dataError !== null) {
            return ['success' => false, 'message' => $this->dataError, 'status' => 503];
        }

        // nearby (của property)
        $near = $this->db->select('nearby_places', [
            'select'  => 'id,name,category,short_description,distance_meters,walking_minutes,image_url,featured',
            'filters' => Pg::eqInt('property_id', (int)$unit['property_id']),
            'order'   => 'distance_meters.asc',
            'limit'   => 50,
        ]);
        if (!$near['ok']) {
            Security::logError('units', 'detail_nearby_failed', ['status' => $near['status'] ?? 0]);
            return ['success' => false, 'message' => 'Không thể tải thông tin khu vực lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $unit['nearby_places'] = is_array($near['data']) ? $near['data'] : [];

        return ['success' => true, 'data' => $unit, 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * SUPPORT LOOKUPS
     * ──────────────────────────────────────────────────────────────── */
    public function ratingMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $res = $this->db->select('v_unit_rating', ['select' => 'unit_id,avg_rating,review_count', 'limit' => 100]);
        $cache = [];
        foreach (($res['ok'] ? $res['data'] : []) as $row) {
            $cache[(int)$row['unit_id']] = ['avg_rating' => (float)$row['avg_rating'], 'review_count' => (int)$row['review_count']];
        }
        return $cache;
    }

    public function tagMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $res = $this->db->select('experience_tags', ['select' => 'id,code', 'limit' => 100]);
        if (!$res['ok']) {
            Security::logError('units', 'tag_map_failed', ['status' => $res['status'] ?? 0]);
            $this->dataError = 'Không thể áp dụng bộ lọc trải nghiệm lúc này. Vui lòng thử lại.';
            return [];
        }
        $cache = [];
        foreach ((is_array($res['data']) ? $res['data'] : []) as $row) {
            $cache[(string)$row['code']] = (int)$row['id'];
        }
        return $cache;
    }

    private function unitsWithTag(int $tagId): array
    {
        $res = $this->db->select('unit_experience_tags', [
            'select'  => 'unit_id',
            'filters' => Pg::eqInt('tag_id', $tagId),
            'limit'   => 200,
        ]);
        if (!$res['ok']) {
            Security::logError('units', 'tag_units_failed', ['status' => $res['status'] ?? 0]);
            $this->dataError = 'Không thể áp dụng bộ lọc trải nghiệm lúc này. Vui lòng thử lại.';
            return [];
        }
        return array_map('intval', array_column((is_array($res['data']) ? $res['data'] : []), 'unit_id'));
    }

    private function amenitiesForUnit(int $unitId): array
    {
        $join = $this->db->select('unit_amenities', [
            'select'  => 'amenity_id',
            'filters' => Pg::eqInt('unit_id', $unitId),
            'limit'   => 50,
        ]);
        if (!$join['ok']) {
            Security::logError('units', 'amenities_join_failed', ['status' => $join['status'] ?? 0]);
            $this->dataError = 'Không thể tải tiện nghi lúc này. Vui lòng thử lại.';
            return [];
        }
        $ids = array_map('intval', array_column((is_array($join['data']) ? $join['data'] : []), 'amenity_id'));
        if (!$ids) {
            return [];
        }
        $res = $this->db->select('amenities', [
            'select'  => 'id,code,name_vi,name_en,icon,category',
            'filters' => [Pg::inInts('id', $ids), Pg::eqStr('active', 'true')],
            'limit'   => 50,
        ]);
        if (!$res['ok']) {
            Security::logError('units', 'amenities_lookup_failed', ['status' => $res['status'] ?? 0]);
            $this->dataError = 'Không thể tải tiện nghi lúc này. Vui lòng thử lại.';
            return [];
        }
        return is_array($res['data']) ? $res['data'] : [];
    }

    private function tagsForUnit(int $unitId): array
    {
        $join = $this->db->select('unit_experience_tags', [
            'select'  => 'tag_id,weight',
            'filters' => Pg::eqInt('unit_id', $unitId),
            'order'   => 'weight.desc',
            'limit'   => 30,
        ]);
        if (!$join['ok']) {
            Security::logError('units', 'experience_tags_join_failed', ['status' => $join['status'] ?? 0]);
            $this->dataError = 'Không thể tải thông tin trải nghiệm lúc này. Vui lòng thử lại.';
            return [];
        }
        $rows = is_array($join['data']) ? $join['data'] : [];
        $ids = array_map('intval', array_column($rows, 'tag_id'));
        if (!$ids) {
            return [];
        }
        $res = $this->db->select('experience_tags', [
            'select'  => 'id,code,name_vi,name_en,category',
            'filters' => Pg::inInts('id', $ids),
            'limit'   => 30,
        ]);
        if (!$res['ok']) {
            Security::logError('units', 'experience_tags_lookup_failed', ['status' => $res['status'] ?? 0]);
            $this->dataError = 'Không thể tải thông tin trải nghiệm lúc này. Vui lòng thử lại.';
            return [];
        }
        $tags = is_array($res['data']) ? $res['data'] : [];
        $weightMap = [];
        foreach ($rows as $r) {
            $weightMap[(int)$r['tag_id']] = (int)$r['weight'];
        }
        foreach ($tags as &$t) {
            $t['weight'] = $weightMap[(int)$t['id']] ?? 5;
        }
        return $tags;
    }

    private function reviewsForUnit(int $unitId): array
    {
        $res = $this->db->select('reviews', [
            'select'  => 'id,booking_id,customer_id,unit_id,overall_rating,cleanliness_rating,location_rating,amenities_rating,accuracy_rating,communication_rating,value_rating,title,comment,status,created_at',
            'filters' => [Pg::eqInt('unit_id', $unitId), Pg::eqStr('status', 'approved')],
            'order'   => 'created_at.desc',
            'limit'   => 50,
        ]);
        if (!$res['ok']) {
            Security::logError('units', 'reviews_lookup_failed', ['status' => $res['status'] ?? 0]);
            $this->dataError = 'Không thể tải đánh giá lúc này. Vui lòng thử lại.';
            return [];
        }
        $reviews = is_array($res['data']) ? $res['data'] : [];
        if (!$reviews) return [];

        $customerIds = array_values(array_unique(array_map('intval', array_column($reviews, 'customer_id'))));
        $nameMap = [];
        if ($customerIds) {
            $cust = $this->db->select('customers', [
                'select'  => 'id,full_name,avatar_url',
                'filters' => Pg::inInts('id', $customerIds),
                'limit'   => 50,
            ]);
            if (!$cust['ok']) {
                Security::logError('units', 'review_customers_failed', ['status' => $cust['status'] ?? 0]);
                $this->dataError = 'Không thể tải thông tin đánh giá lúc này. Vui lòng thử lại.';
                return [];
            }
            foreach ((is_array($cust['data']) ? $cust['data'] : []) as $c) {
                $nameMap[(int)$c['id']] = $c;
            }
        }

        // "Lưu trú đã xác minh" chỉ đúng khi review thực sự trỏ tới booking
        // checkout của đúng customer và đúng unit.
        $bookingIds = array_values(array_unique(array_filter(array_map('intval', array_column($reviews, 'booking_id')), static fn($id) => $id > 0)));
        $bookingMap = [];
        if ($bookingIds) {
            $bookings = $this->db->select('bookings', [
                'select'  => 'id,customer_id,unit_id,status,check_in,check_out',
                'filters' => Pg::inInts('id', $bookingIds),
                'limit'   => 50,
            ]);
            if (!$bookings['ok']) {
                Security::logError('units', 'review_bookings_failed', ['status' => $bookings['status'] ?? 0]);
                $this->dataError = 'Không thể xác minh đánh giá lúc này. Vui lòng thử lại.';
                return [];
            }
            foreach ((is_array($bookings['data']) ? $bookings['data'] : []) as $b) {
                $bookingMap[(int)$b['id']] = $b;
            }
        }

        foreach ($reviews as &$rv) {
            $rv['customer'] = $nameMap[(int)$rv['customer_id']] ?? null;
            $b = $bookingMap[(int)($rv['booking_id'] ?? 0)] ?? null;
            $rv['verified_stay'] = is_array($b)
                && (int)$b['customer_id'] === (int)$rv['customer_id']
                && (int)$b['unit_id'] === $unitId
                && (string)$b['status'] === 'checkout';
            if ($rv['verified_stay']) {
                $rv['stay_check_in'] = $b['check_in'] ?? null;
                $rv['stay_check_out'] = $b['check_out'] ?? null;
            }
        }
        unset($rv);
        return $reviews;
    }

}
