<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * YANGMATCH SERVICE — "Tìm căn hợp gu" (deterministic, KHÔNG dùng AI bên ngoài)
 *
 * Input quiz:
 *   q1 companion: solo | couple | friends | family | business
 *   q2 style:     quiet | romantic | vintage | modern | vibrant | nature
 *   q3 amenities: ['bathtub','balcony','lake_view','kitchen','smart_tv',
 *                  'workspace','washing_machine','elevator','parking']
 *   q4 budget:    max VND/đêm (hoặc 0 = không giới hạn)
 *
 * Score (deterministic, không random):
 *   • tag companion khớp unit_experience_tags.weight × 3
 *   • tag style khớp weight × 2.5 (vibrant → nightlife/food-lover; nature → lake-view)
 *   • mỗi amenity chọn mà unit có: +3
 *   • base_price ≤ budget: +6 (trong +20%: +3)
 *   • avg_rating: × 2
 *   % = min(99, round(raw / 100 × 100))
 * ═════════════════════════════════════════════════════════════════════════
 */
final class YangMatchService
{
    private SupabaseClient $db;

    private const COMPANION_TAGS = ['solo', 'couple', 'friends', 'family', 'business'];
    private const STYLE_TAGS = ['quiet', 'romantic', 'vintage', 'modern'];

    public function __construct()
    {
        $this->db = supabase();
    }

    public function match(array $answers, int $limit = 3): array
    {
        $companion = Pg::whitelist($answers['q1'] ?? null, self::COMPANION_TAGS, 'couple');
        $style     = Pg::whitelist($answers['q2'] ?? null, ['quiet', 'romantic', 'vintage', 'modern', 'vibrant', 'nature'], 'quiet');
        $amenityCodes = array_values(array_intersect(
            is_array($answers['q3'] ?? null) ? $answers['q3'] : [],
            ['bathtub', 'balcony', 'lake_view', 'kitchen', 'smart_tv', 'workspace', 'washing_machine', 'elevator', 'parking']
        ));
        $budget = (int)($answers['q4'] ?? 0);
        if ($budget < 0) {
            return ['success' => false, 'message' => 'Ngân sách không hợp lệ.', 'status' => 422];
        }
        $limit = max(1, min(6, $limit));

        // Companion không có input "số người", nên dùng minimum capacity có thể
        // giải thích khi bảo vệ đồ án. Đây là hard constraint trước khi scoring.
        $minimumGuests = [
            'solo' => 1,
            'couple' => 2,
            'friends' => 3,
            'family' => 3,
            'business' => 1,
        ][$companion] ?? 1;

        // ── Data ──
        $units = $this->db->select('units', [
            'select'  => 'id,property_id,name,slug,type,base_price,max_guests,status',
            'filters' => Pg::eqStr('status', 'active'),
            'limit'   => 50,
        ]);
        if (!$units['ok']) {
            Security::logError('yangmatch', 'units_failed', ['status' => $units['status'] ?? 0]);
            return ['success' => false, 'message' => 'YangMatch chưa thể tải dữ liệu lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $units = is_array($units['data']) ? $units['data'] : [];

        // Hard capacity constraint trước scoring.
        $units = array_values(array_filter(
            $units,
            static fn(array $u): bool => (int)($u['max_guests'] ?? 0) >= $minimumGuests
        ));

        // Cover image để kết quả YangMatch không rơi về placeholder.
        $unitIds = array_map('intval', array_column($units, 'id'));
        $coverMap = [];
        if ($unitIds) {
            $images = $this->db->select('unit_images', [
                'select'  => 'unit_id,image_url,caption,is_cover,display_order',
                'filters' => Pg::inInts('unit_id', $unitIds),
                'order'   => 'is_cover.desc,display_order.asc',
                'limit'   => 300,
            ]);
            if (!$images['ok']) {
                Security::logError('yangmatch', 'images_failed', ['status' => $images['status'] ?? 0]);
                return ['success' => false, 'message' => 'YangMatch chưa thể tải hình ảnh lúc này. Vui lòng thử lại.', 'status' => 503];
            }
            foreach ((is_array($images['data']) ? $images['data'] : []) as $img) {
                $uid = (int)$img['unit_id'];
                if (!isset($coverMap[$uid])) $coverMap[$uid] = $img;
            }
        }

        $props = $this->db->select('properties', [
            'select'  => 'id,name,slug,district',
            'filters' => Pg::eqStr('status', 'active'),
            'limit'   => 50,
        ]);
        if (!$props['ok']) {
            Security::logError('yangmatch', 'properties_failed', ['status' => $props['status'] ?? 0]);
            return ['success' => false, 'message' => 'YangMatch chưa thể tải khu vực lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $propMap = [];
        foreach ((is_array($props['data']) ? $props['data'] : []) as $p) {
            $propMap[(int)$p['id']] = $p;
        }

        $tags = $this->db->select('experience_tags', ['select' => 'id,code,name_vi', 'limit' => 50]);
        if (!$tags['ok']) {
            Security::logError('yangmatch', 'tags_failed', ['status' => $tags['status'] ?? 0]);
            return ['success' => false, 'message' => 'YangMatch chưa thể tải tiêu chí phong cách lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $tagIdToCode = [];
        foreach ((is_array($tags['data']) ? $tags['data'] : []) as $t) {
            $tagIdToCode[(int)$t['id']] = $t;
        }

        $uet = $this->db->select('unit_experience_tags', ['select' => 'unit_id,tag_id,weight', 'limit' => 500]);
        if (!$uet['ok']) {
            Security::logError('yangmatch', 'unit_tags_failed', ['status' => $uet['status'] ?? 0]);
            return ['success' => false, 'message' => 'YangMatch chưa thể tải dữ liệu ghép gu lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $unitTags = [];
        foreach ((is_array($uet['data']) ? $uet['data'] : []) as $row) {
            $unitTags[(int)$row['unit_id']][(int)$row['tag_id']] = (int)$row['weight'];
        }

        $ua = $this->db->select('unit_amenities', ['select' => 'unit_id,amenity_id', 'limit' => 500]);
        if (!$ua['ok']) {
            Security::logError('yangmatch', 'unit_amenities_failed', ['status' => $ua['status'] ?? 0]);
            return ['success' => false, 'message' => 'YangMatch chưa thể tải dữ liệu tiện nghi lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $unitAmenityIds = [];
        foreach ((is_array($ua['data']) ? $ua['data'] : []) as $row) {
            $unitAmenityIds[(int)$row['unit_id']][] = (int)$row['amenity_id'];
        }

        $am = $this->db->select('amenities', ['select' => 'id,code', 'limit' => 50]);
        if (!$am['ok']) {
            Security::logError('yangmatch', 'amenities_failed', ['status' => $am['status'] ?? 0]);
            return ['success' => false, 'message' => 'YangMatch chưa thể tải danh mục tiện nghi lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $amenityIdToCode = [];
        foreach ((is_array($am['data']) ? $am['data'] : []) as $a) {
            $amenityIdToCode[(int)$a['id']] = (string)$a['code'];
        }

        $rating = (new UnitService())->ratingMap();

        // ── Score ──
        $results = [];
        foreach ($units as $u) {
            $id = (int)$u['id'];
            if (!isset($propMap[(int)$u['property_id']])) {
                continue;
            }
            $score = 0.0;
            $reasons = [];

            if ($minimumGuests > 1) {
                $reasons[] = [
                    'icon' => '🛏️',
                    'text' => 'Sức chứa phù hợp nhóm ' . $minimumGuests . '+ khách',
                ];
            }

            // companion
            $compTagId = $this->tagIdByCode($tagIdToCode, $companion);
            if ($compTagId !== null && isset($unitTags[$id][$compTagId])) {
                $w = $unitTags[$id][$compTagId];
                $score += $w * 3;
                $reasons[] = ['icon' => '👥', 'text' => $this->tagName($tagIdToCode, $compTagId) . ' (mức ' . $w . '/10)'];
            }

            // style
            if (in_array($style, self::STYLE_TAGS, true)) {
                $sid = $this->tagIdByCode($tagIdToCode, $style);
                if ($sid !== null && isset($unitTags[$id][$sid])) {
                    $w = $unitTags[$id][$sid];
                    $score += $w * 2.5;
                    $reasons[] = ['icon' => '🎨', 'text' => $this->tagName($tagIdToCode, $sid) . ' (mức ' . $w . '/10)'];
                }
            } elseif ($style === 'vibrant') {
                foreach (['nightlife', 'food-lover'] as $vTag) {
                    $vid = $this->tagIdByCode($tagIdToCode, $vTag);
                    if ($vid !== null && isset($unitTags[$id][$vid])) {
                        $score += $unitTags[$id][$vid] * 2;
                        $reasons[] = ['icon' => '🌃', 'text' => $this->tagName($tagIdToCode, $vid)];
                    }
                }
            } elseif ($style === 'nature') {
                $nid = $this->tagIdByCode($tagIdToCode, 'lake-view');
                if ($nid !== null && isset($unitTags[$id][$nid])) {
                    $score += $unitTags[$id][$nid] * 2.5;
                    $reasons[] = ['icon' => '🌿', 'text' => $this->tagName($tagIdToCode, $nid)];
                }
            }

            // amenities
            $haveAmenities = [];
            foreach (($unitAmenityIds[$id] ?? []) as $aid) {
                $code = $amenityIdToCode[$aid] ?? null;
                if ($code !== null) {
                    $haveAmenities[] = $code;
                }
            }
            $matchedAmenities = array_values(array_intersect($amenityCodes, $haveAmenities));
            $score += count($matchedAmenities) * 3;
            foreach ($matchedAmenities as $ma) {
                $reasons[] = ['icon' => '✓', 'text' => $this->amenityLabel($ma)];
            }

            // budget: hard constraint có dung sai 20% để không gợi ý căn quá xa ngân sách.
            $price = (int)$u['base_price'];
            if ($budget > 0 && $price > (int)round($budget * 1.2)) {
                continue;
            }
            if ($budget > 0) {
                if ($price <= $budget) {
                    $score += 6;
                    $reasons[] = ['icon' => '💰', 'text' => 'Trong ngân sách (' . PricingService::formatVnd($price) . '/đêm)'];
                } else {
                    $score += 3;
                    $reasons[] = ['icon' => '💰', 'text' => 'Nhỉnh hơn ngân sách nhưng trong mức +20%'];
                }
            }

            // rating
            $avg = (float)($rating[$id]['avg_rating'] ?? 0);
            $score += $avg * 2;

            $results[] = [
                'unit' => array_merge($u, [
                    'property'   => $propMap[(int)$u['property_id']] ?? null,
                    'avg_rating' => $avg,
                    'review_count'=> (int)($rating[$id]['review_count'] ?? 0),
                    'cover_image' => $coverMap[$id] ?? null,
                ]),
                'score_raw'   => round($score, 1),
                'score_pct'   => min(99, (int)round($score)),
                'reasons'     => array_slice($reasons, 0, 5),
            ];
        }

        usort($results, fn($a, $b) => $b['score_raw'] <=> $a['score_raw']);

        return [
            'success' => true,
            'data' => [
                'answers' => [
                    'q1' => $companion,
                    'q2' => $style,
                    'q3' => $amenityCodes,
                    'q4' => $budget,
                    'minimum_guests' => $minimumGuests,
                ],
                'matches' => array_slice($results, 0, $limit),
            ],
            'status' => 200,
        ];
    }

    private function tagIdByCode(array $tagIdToCode, string $code): ?int
    {
        foreach ($tagIdToCode as $id => $tag) {
            if (($tag['code'] ?? '') === $code) {
                return (int)$id;
            }
        }
        return null;
    }

    private function tagName(array $tagIdToCode, int $id): string
    {
        return (string)($tagIdToCode[$id]['name_vi'] ?? '');
    }

    private function amenityLabel(string $code): string
    {
        $labels = [
            'bathtub' => 'Bồn tắm', 'balcony' => 'Ban công', 'lake_view' => 'View hồ',
            'kitchen' => 'Bếp', 'smart_tv' => 'Smart TV', 'workspace' => 'Bàn làm việc',
            'washing_machine' => 'Máy giặt', 'elevator' => 'Thang máy', 'parking' => 'Chỗ đỗ xe',
        ];
        return $labels[$code] ?? $code;
    }
}
