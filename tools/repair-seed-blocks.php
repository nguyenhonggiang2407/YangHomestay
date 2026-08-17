<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * YANGHOMESTAY — Sửa seed: khôi phục 4 block bị mất
 *   (5. UNITS, 6. UNIT_IMAGES, 7. AMENITIES, 8. UNIT_AMENITIES)
 * Nguồn dữ liệu: live Supabase DB (đang đúng 16 units / 18 amenities / 152 links).
 * Chạy:  php tools/repair-seed-blocks.php
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../backend/bootstrap.php';

$db = supabase();

function q(string $v): string
{
    return "'" . str_replace("'", "''", $v) . "'";
}
function qn(?string $v): string
{
    return $v === null || $v === '' ? 'NULL' : q($v);
}

/* ── 5. UNITS ── */
$cols = 'id,property_id,code,name,slug,type,base_price,cleaning_fee,max_guests,bedrooms,beds,bathrooms,area_m2,short_description,description,house_rules,status,featured,created_at';
$r = $db->select('units', ['select' => $cols, 'order' => 'id.asc', 'limit' => 50]);
$units = $r['data'] ?? [];
$lines = [];
foreach ($units as $u) {
    $lines[] = '(' . $u['id'] . ', ' . $u['property_id'] . ', ' . q($u['code']) . ', ' . q($u['name']) . ', ' . q($u['slug']) . ', ' . q($u['type'])
        . ', ' . $u['base_price'] . ', ' . $u['cleaning_fee'] . ', ' . $u['max_guests'] . ', ' . $u['bedrooms'] . ', ' . $u['beds'] . ', ' . $u['bathrooms']
        . ', ' . ($u['area_m2'] === null ? 'NULL' : $u['area_m2'])
        . ', ' . qn($u['short_description'] ?? null) . ', ' . qn($u['description'] ?? null) . ', ' . qn($u['house_rules'] ?? null)
        . ', ' . q($u['status']) . ', ' . ($u['featured'] ? 'TRUE' : 'FALSE') . ', ' . q($u['created_at']) . '),';
}
$unitsBlock = "-- 5. UNITS (16) — đơn vị đặt thực tế; giá VND nguyên; availability suy từ bookings\n"
    . "INSERT INTO public.units (id, property_id, code, name, slug, type, base_price, cleaning_fee, max_guests, bedrooms, beds, bathrooms, area_m2, short_description, description, house_rules, status, featured, created_at) VALUES\n"
    . implode("\n", $lines) . "\n";
$unitsBlock = substr($unitsBlock, 0, -2) . ";\n";

/* ── 6. UNIT_IMAGES — lấy thẳng từ live DB ── */
$r = $db->select('unit_images', ['select' => 'id,unit_id,storage_path,image_url,caption,image_category,display_order,is_cover,created_at', 'order' => 'id.asc', 'limit' => 200]);
$imgs = $r['data'] ?? [];
$lines = [];
foreach ($imgs as $im) {
    $lines[] = '(' . $im['id'] . ', ' . $im['unit_id'] . ', ' . qn($im['storage_path'] ?? null) . ', ' . q($im['image_url'])
        . ', ' . qn($im['caption'] ?? null) . ', ' . q($im['image_category']) . ', ' . $im['display_order'] . ', ' . ($im['is_cover'] ? 'TRUE' : 'FALSE') . ', ' . q($im['created_at']) . '),';
}
$unitImagesBlock = "-- 6. UNIT_IMAGES (" . count($imgs) . ") — ảnh demo local assets/images/homestays/{district}/; storage_path để dành cho bucket `homestay-images`\n"
    . "INSERT INTO public.unit_images (id, unit_id, storage_path, image_url, caption, image_category, display_order, is_cover, created_at) VALUES\n"
    . implode("\n", $lines) . "\n";
$unitImagesBlock = substr($unitImagesBlock, 0, -2) . ";\n";

/* ── 7. AMENITIES ── */
$r = $db->select('amenities', ['select' => 'id,code,name_vi,name_en,icon,category,active,created_at', 'order' => 'id.asc', 'limit' => 50]);
$amens = $r['data'] ?? [];
$lines = [];
foreach ($amens as $a) {
    $lines[] = '(' . $a['id'] . ', ' . q($a['code']) . ', ' . q($a['name_vi']) . ', ' . q($a['name_en']) . ', ' . qn($a['icon'] ?? null)
        . ', ' . q($a['category']) . ', ' . ($a['active'] ? 'TRUE' : 'FALSE') . ', ' . q($a['created_at']) . '),';
}
$amenitiesBlock = "-- 7. AMENITIES (18) — danh mục tiện nghi dùng chung\n"
    . "INSERT INTO public.amenities (id, code, name_vi, name_en, icon, category, active, created_at) VALUES\n"
    . implode("\n", $lines) . "\n";
$amenitiesBlock = substr($amenitiesBlock, 0, -2) . ";\n";

/* ── 8. UNIT_AMENITIES ── */
$r = $db->select('unit_amenities', ['select' => 'unit_id,amenity_id', 'limit' => 300]);
$links = $r['data'] ?? [];
$lines = [];
foreach ($links as $l) {
    $lines[] = '(' . $l['unit_id'] . ', ' . $l['amenity_id'] . '),';
}
$unitAmenitiesBlock = "-- 8. UNIT_AMENITIES (" . count($links) . ") — gắn tiện nghi theo \"gu\" từng unit\n"
    . "INSERT INTO public.unit_amenities (unit_id, amenity_id) VALUES\n"
    . implode("\n", $lines) . "\n";
$unitAmenitiesBlock = substr($unitAmenitiesBlock, 0, -2) . ";\n";

/* ── 9. EXPERIENCE_TAGS (bị xóa nhầm cùng block 5-8) ── */
$r = $db->select('experience_tags', ['select' => 'id,code,name_vi,name_en,category,active', 'order' => 'id.asc', 'limit' => 50]);
$tags = $r['data'] ?? [];
$lines = [];
foreach ($tags as $t) {
    $lines[] = '(' . $t['id'] . ', ' . q($t['code']) . ', ' . q($t['name_vi']) . ', ' . q($t['name_en']) . ', ' . q($t['category']) . ', ' . ($t['active'] ? 'TRUE' : 'FALSE') . '),';
}
$tagsBlock = "-- 9. EXPERIENCE_TAGS (" . count($tags) . ") — đầu vào cho YangMatch\n"
    . "INSERT INTO public.experience_tags (id, code, name_vi, name_en, category, active) VALUES\n"
    . implode("\n", $lines) . "\n";
$tagsBlock = substr($tagsBlock, 0, -2) . ";\n";

/* ── Chèn vào seed giữa block PROPERTY_IMAGES và EXPERIENCE_TAGS ── */
$seedFile = __DIR__ . '/../database/supabase/002_seed.sql';
$sql = file_get_contents($seedFile);
if ($sql === false) { fwrite(STDERR, "Cannot read seed file\n"); exit(1); }

// xóa block cũ 5-9 (nếu có sót) rồi chèn lại ngay trước section 10
$blockPattern = '/-- 5\. UNITS.*?(?=-- 10\. UNIT_EXPERIENCE_TAGS)/s';
$newSection = $unitsBlock . "\n" . $unitImagesBlock . "\n" . $amenitiesBlock . "\n" . $unitAmenitiesBlock . "\n" . $tagsBlock . "\n";

if (preg_match($blockPattern, $sql)) {
    $sql = preg_replace($blockPattern, $newSection, $sql, 1);
} else {
    // không tìm thấy block cũ — chèn trước comment section 10
    $anchor = "-- 10. UNIT_EXPERIENCE_TAGS";
    $pos = strpos($sql, $anchor);
    if ($pos === false) { fwrite(STDERR, "anchor not found\n"); exit(1); }
    $sql = substr($sql, 0, $pos) . $newSection . substr($sql, $pos);
}

file_put_contents($seedFile, $sql);
echo "seed repaired: units=" . count($units) . " unit_images=" . count($imgs) . " amenities=" . count($amens) . " unit_amenities=" . count($links) . " tags=" . count($tags) . "\n";
