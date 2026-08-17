<?php
/**
 * ═════════════════════════════════════════════════════════════════════════
 * YANGHOMESTAY — Demo image generator (v2 — district + price/amenity aware)
 *
 * Sinh ảnh minh họa theo KHU VỰC (district) + dữ liệu thật của unit:
 *   assets/images/homestays/
 *   ├── old-quarter/  truc-bach/  west-lake/  dong-da/  cau-giay/
 *   │     ├── scene-{living,living-luxury,bedroom,bedroom-luxury,bathroom,
 *   │     │          bathroom-luxury,balcony,balcony-lake,view,kitchen,
 *   │     │          workspace,dining}.jpg
 *   │     ├── unit-{id}-cover.jpg
 *   │     └── property-{id}-cover.jpg
 *   ├── fallback/unit-fallback.jpg · property-fallback.jpg
 *   └── _src/*.svg   (nguồn vector — giữ để tái sinh)
 *
 * Pipeline raster: SVG → headless Chrome (PNG) → PowerShell System.Drawing (JPG q90)
 *
 * Chạy:  php tools/generate-demo-images.php
 * ═════════════════════════════════════════════════════════════════════════
 */

$root = realpath(__DIR__ . '/..');
$outRoot = $root . '/assets/images/homestays';
$svgSrc = $outRoot . '/_src';
if (!is_dir($svgSrc)) { mkdir($svgSrc, 0777, true); }

/* ═════════════════════════════════════════════════════════════════════════
   PALETTES — 5 khu vực (warm, premium, tương phản cao hơn palette cũ)
   ═════════════════════════════════════════════════════════════════════════ */
$palettes = [
    'old-quarter' => [ // Hoàn Kiếm — vintage ấm, gỗ, đèn vàng
        'bg1' => '#F5EBDD', 'bg2' => '#EBDCC4', 'wall' => '#F0E2CC', 'wood' => '#9A6B43',
        'accent' => '#B96F4A', 'deep' => '#6E4428', 'warm' => '#D9A441', 'sky1' => '#F6DEB0', 'sky2' => '#E9BE7E', 'sky3' => '#D3935C',
    ],
    'truc-bach' => [ // Ba Đình — xanh sage tĩnh lặng
        'bg1' => '#F2EEE3', 'bg2' => '#E2E2CE', 'wall' => '#E9E7D4', 'wood' => '#8E7A52',
        'accent' => '#5E7B6B', 'deep' => '#3F5A4E', 'warm' => '#C2A25E', 'sky1' => '#EEDFB4', 'sky2' => '#DCC898', 'sky3' => '#BFA475',
    ],
    'west-lake' => [ // Tây Hồ — hoàng hôn vàng hồ
        'bg1' => '#F7EDDF', 'bg2' => '#EBD9BE', 'wall' => '#F1E3CC', 'wood' => '#A0714B',
        'accent' => '#C0754F', 'deep' => '#7A4528', 'warm' => '#D9A441', 'sky1' => '#F8DCA6', 'sky2' => '#EFA96F', 'sky3' => '#D97B52',
    ],
    'dong-da' => [ // Đống Đa — olive đô thị
        'bg1' => '#F1EDE2', 'bg2' => '#DFDFC9', 'wall' => '#E8E5D2', 'wood' => '#8A744E',
        'accent' => '#6E7A52', 'deep' => '#41492F', 'warm' => '#C09A54', 'sky1' => '#EDDBB0', 'sky2' => '#D9BC85', 'sky3' => '#B79A6C',
    ],
    'cau-giay' => [ // Cầu Giấy — hiện đại, sạch, teal xám
        'bg1' => '#F2F0EA', 'bg2' => '#E0E0D6', 'wall' => '#E9E8DE', 'wood' => '#9A8A66',
        'accent' => '#5F7D78', 'deep' => '#3B5552', 'warm' => '#C3A25C', 'sky1' => '#EFDFB6', 'sky2' => '#DCC393', 'sky3' => '#BCA174',
    ],
];

/* ═════════════════════════════════════════════════════════════════════════
   HELPERS
   ═════════════════════════════════════════════════════════════════════════ */
function rr(float $x, float $y, float $w, float $h, float $r, string $fill, string $opacity = '1'): string
{
    return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $r . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function circ(float $cx, float $cy, float $r, string $fill, string $opacity = '1'): string
{
    return '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function ell(float $cx, float $cy, float $rx, float $ry, string $fill, string $opacity = '1'): string
{
    return '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function poly(array $pts, string $fill, string $opacity = '1'): string
{
    return '<polygon points="' . implode(',', $pts) . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function windowSky(array $p, float $x, float $y, float $w, float $h): string
{
    $s = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="10" fill="' . $p['wall'] . '"/>';
    $s .= '<rect x="' . ($x + 10) . '" y="' . ($y + 10) . '" width="' . ($w - 20) . '" height="' . ($h - 20) . '" rx="6" fill="url(#sky)"/>';
    $s .= circ($x + $w / 2, $y + $h * 0.42, 14, $p['warm'], '0.9');
    $s .= '<path d="M' . ($x + 12) . ' ' . ($y + $h - 10) . ' L' . ($x + $w / 2 - 30) . ' ' . ($y + $h * 0.55) . ' Q' . ($x + $w / 2) . ' ' . ($y + $h * 0.62) . ' ' . ($x + $w / 2 + 34) . ' ' . ($y + $h * 0.55) . ' L' . ($x + $w - 12) . ' ' . ($y + $h - 10) . ' Z" fill="' . $p['deep'] . '" opacity="0.5"/>';
    $s .= '<line x1="' . ($x + $w / 2) . '" y1="' . ($y + 8) . '" x2="' . ($x + $w / 2) . '" y2="' . ($y + $h - 8) . '" stroke="' . $p['wall'] . '" stroke-width="6"/>';
    return $s;
}
function plant(float $x, float $y, array $p): string
{
    $s = '<rect x="' . $x . '" y="' . $y . '" width="34" height="26" rx="6" fill="' . $p['deep'] . '"/>';
    $s .= '<ellipse cx="' . ($x + 8) . '" cy="' . ($y - 14) . '" rx="9" ry="15" fill="' . $p['accent'] . '" transform="rotate(-24 ' . ($x + 8) . ' ' . ($y - 14) . ')"/>';
    $s .= '<ellipse cx="' . ($x + 20) . '" cy="' . ($y - 20) . '" rx="9" ry="16" fill="' . $p['accent'] . '" transform="rotate(8 ' . ($x + 20) . ' ' . ($y - 20) . ')"/>';
    $s .= '<ellipse cx="' . ($x + 28) . '" cy="' . ($y - 10) . '" rx="7" ry="12" fill="' . $p['deep'] . '" transform="rotate(28 ' . ($x + 28) . ' ' . ($y - 10) . ')"/>';
    return $s;
}
function baseRoom(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 430, 0, $p['wall']);
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.32');
    $s .= rr(0, 424, 800, 8, 0, $p['wood'], '0.5');
    return $s;
}

/* ═════════════════════════════════════════════════════════════════════════
   SCENES (12)
   ═════════════════════════════════════════════════════════════════════════ */
function sceneLiving(array $p): string
{
    $s = baseRoom($p);
    $s .= windowSky($p, 60, 70, 200, 240);
    $s .= rr(500, 90, 130, 96, 10, $p['warm'], '0.5');
    $s .= rr(512, 102, 30, 72, 4, $p['accent'], '0.75');
    $s .= ell(430, 500, 210, 46, $p['warm'], '0.25');
    $s .= rr(300, 320, 300, 110, 22, $p['deep']);
    $s .= rr(286, 300, 60, 130, 24, $p['accent']);
    $s .= rr(554, 300, 60, 130, 24, $p['accent']);
    $s .= rr(318, 300, 264, 46, 20, $p['deep']);
    $s .= rr(340, 292, 70, 44, 12, $p['warm'], '0.9');
    $s .= rr(452, 292, 70, 44, 12, $p['warm'], '0.9');
    $s .= rr(640, 420, 90, 12, 6, $p['wood']);
    $s .= rr(652, 432, 10, 60, 4, $p['wood']);
    $s .= rr(708, 432, 10, 60, 4, $p['wood']);
    $s .= circ(685, 416, 14, $p['warm']);
    $s .= rr(236, 300, 8, 130, 4, $p['wood']);
    $s .= poly([214, 300, 266, 300, 252, 262, 228, 262], $p['accent']);
    $s .= plant(520, 470, $p);
    return $s;
}

function sceneLivingLuxury(array $p): string
{
    $s = baseRoom($p);
    $s .= windowSky($p, 520, 60, 210, 250);
    $s .= ell(360, 515, 240, 48, $p['warm'], '0.22');
    $s .= '<line x1="280" y1="500" x2="440" y2="500" stroke="' . $p['wood'] . '" stroke-width="3" opacity="0.4"/>';
    // sofa dài + chaise
    $s .= rr(200, 300, 340, 120, 24, $p['deep']);
    $s .= rr(184, 278, 64, 140, 26, $p['accent']);
    $s .= rr(540, 278, 120, 90, 22, $p['accent']);
    $s .= rr(216, 278, 300, 50, 20, $p['deep']);
    $s .= rr(240, 268, 76, 46, 14, $p['warm'], '0.95');
    $s .= rr(330, 268, 76, 46, 14, '#FFFDF9');
    $s .= rr(420, 268, 76, 46, 14, $p['warm'], '0.85');
    // bàn trà đá cẩm thạch
    $s .= rr(330, 398, 140, 12, 6, '#FFFDF9');
    $s .= rr(348, 410, 12, 46, 4, $p['wood']);
    $s .= rr(440, 410, 12, 46, 4, $p['wood']);
    // đèn chùm
    $s .= '<line x1="400" y1="0" x2="400" y2="120" stroke="' . $p['deep'] . '" stroke-width="3"/>';
    $s .= circ(400, 140, 26, $p['warm'], '0.95');
    $s .= circ(400, 140, 12, $p['deep'], '0.85');
    // tranh lớn
    $s .= rr(80, 90, 150, 110, 12, $p['deep'], '0.9');
    $s .= rr(96, 106, 118, 78, 6, $p['warm'], '0.6');
    // sideboard
    $s .= rr(600, 420, 130, 14, 6, $p['wood']);
    $s .= rr(614, 434, 14, 54, 4, $p['wood']);
    $s .= rr(702, 434, 14, 54, 4, $p['wood']);
    $s .= circ(665, 418, 10, $p['accent']);
    $s .= plant(120, 460, $p);
    return $s;
}

function sceneBedroom(array $p): string
{
    $s = baseRoom($p);
    $s .= windowSky($p, 580, 60, 170, 220);
    $s .= ell(330, 520, 200, 42, $p['warm'], '0.25');
    $s .= rr(150, 250, 360, 60, 18, $p['wood']);
    $s .= rr(150, 310, 360, 130, 16, $p['deep']);
    $s .= rr(165, 296, 330, 40, 14, $p['warm'], '0.9');
    $s .= rr(180, 262, 96, 52, 14, '#FFFFFF');
    $s .= rr(192, 268, 72, 40, 12, $p['accent'], '0.5');
    $s .= rr(384, 262, 96, 52, 14, '#FFFFFF');
    $s .= rr(396, 268, 72, 40, 12, $p['accent'], '0.5');
    $s .= rr(180, 396, 300, 26, 10, $p['accent'], '0.85');
    $s .= rr(165, 440, 16, 26, 4, $p['wood']);
    $s .= rr(479, 440, 16, 26, 4, $p['wood']);
    $s .= rr(100, 380, 70, 70, 10, $p['wood']);
    $s .= rr(112, 348, 12, 36, 4, $p['wood']);
    $s .= poly([104, 348, 132, 348, 126, 326, 110, 326], $p['accent']);
    $s .= circ(118, 322, 5, $p['warm']);
    return $s;
}

function sceneBedroomLuxury(array $p): string
{
    $s = baseRoom($p);
    $s .= windowSky($p, 520, 60, 200, 230);
    $s .= ell(320, 525, 230, 46, $p['warm'], '0.22');
    // đầu giường bọc nệm
    $s .= rr(140, 220, 380, 80, 22, $p['accent']);
    $s .= rr(140, 290, 380, 150, 18, $p['deep']);
    $s .= rr(158, 274, 344, 42, 16, '#FFFDF9');
    // 3 gối
    $s .= rr(172, 234, 90, 54, 14, '#FFFDF9');
    $s .= rr(184, 240, 66, 42, 12, $p['warm'], '0.9');
    $s .= rr(366, 234, 90, 54, 14, '#FFFDF9');
    $s .= rr(378, 240, 66, 42, 12, $p['warm'], '0.9');
    $s .= rr(270, 234, 96, 54, 14, '#FFFDF9');
    // chăn + vén
    $s .= rr(160, 396, 340, 30, 12, $p['warm'], '0.85');
    $s .= rr(158, 440, 20, 24, 4, $p['wood']);
    $s .= rr(482, 440, 20, 24, 4, $p['wood']);
    // bàn đèn hai bên
    $s .= rr(86, 370, 66, 74, 10, $p['wood']);
    $s .= rr(96, 336, 12, 38, 4, $p['wood']);
    $s .= poly([88, 336, 116, 336, 110, 314, 94, 314], $p['warm']);
    $s .= rr(508, 370, 66, 74, 10, $p['wood']);
    $s .= rr(552, 336, 12, 38, 4, $p['wood']);
    $s .= poly([544, 336, 572, 336, 566, 314, 550, 314], $p['warm']);
    // ghế dài cuối giường
    $s .= rr(240, 452, 190, 24, 10, $p['accent']);
    $s .= rr(256, 476, 14, 20, 4, $p['wood']);
    $s .= rr(400, 476, 14, 20, 4, $p['wood']);
    $s .= plant(70, 470, $p);
    return $s;
}

function sceneBathroom(array $p): string
{
    $s = baseRoom($p);
    for ($y = 0; $y < 430; $y += 46) {
        $s .= '<line x1="0" y1="' . $y . '" x2="800" y2="' . $y . '" stroke="' . $p['wood'] . '" stroke-width="2" opacity="0.22"/>';
    }
    for ($x = 0; $x < 800; $x += 100) {
        $s .= '<line x1="' . $x . '" y1="0" x2="' . $x . '" y2="430" stroke="' . $p['wood'] . '" stroke-width="2" opacity="0.22"/>';
    }
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.38');
    $s .= circ(640, 180, 62, '#FFFFFF', '0.9');
    $s .= '<circle cx="640" cy="180" r="62" fill="none" stroke="' . $p['wood'] . '" stroke-width="6"/>';
    $s .= rr(180, 330, 360, 110, 60, '#FFFFFF', '0.95');
    $s .= rr(210, 300, 300, 70, 34, $p['accent'], '0.9');
    $s .= rr(216, 306, 288, 40, 26, $p['deep'], '0.7');
    $s .= rr(150, 428, 26, 18, 6, $p['wood']);
    $s .= rr(544, 428, 26, 18, 6, $p['wood']);
    $s .= rr(350, 250, 14, 58, 6, $p['warm']);
    $s .= circ(357, 252, 9, $p['warm']);
    $s .= rr(80, 210, 14, 120, 6, $p['wood']);
    $s .= rr(80, 230, 60, 44, 10, $p['accent'], '0.9');
    $s .= rr(80, 282, 60, 44, 10, $p['warm'], '0.85');
    $s .= plant(660, 440, $p);
    return $s;
}

function sceneBathroomLuxury(array $p): string
{
    $s = baseRoom($p);
    for ($y = 0; $y < 430; $y += 46) {
        $s .= '<line x1="0" y1="' . $y . '" x2="800" y2="' . $y . '" stroke="' . $p['wood'] . '" stroke-width="2" opacity="0.2"/>';
    }
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.4');
    // bồn tắm đứng độc lập lớn
    $s .= ell(360, 420, 150, 46, '#FFFDF9', '0.98');
    $s .= rr(210, 380, 300, 60, 34, '#FFFDF9');
    $s .= rr(240, 350, 240, 50, 26, $p['accent']);
    $s .= rr(246, 356, 228, 30, 18, $p['deep'], '0.75');
    $s .= rr(196, 428, 22, 16, 6, $p['warm']);
    $s .= rr(502, 428, 22, 16, 6, $p['warm']);
    $s .= rr(344, 300, 16, 60, 6, $p['warm']);
    $s .= circ(352, 300, 10, $p['warm']);
    // lavabo đôi
    $s .= rr(540, 360, 170, 16, 6, $p['wood']);
    $s .= rr(556, 376, 14, 52, 4, $p['wood']);
    $s .= rr(680, 376, 14, 52, 4, $p['wood']);
    $s .= ell(580, 356, 24, 14, '#FFFDF9');
    $s .= ell(664, 356, 24, 14, '#FFFDF9');
    $s .= circ(580, 340, 6, $p['warm']); $s .= circ(664, 340, 6, $p['warm']);
    // gương lớn
    $s .= rr(548, 240, 156, 90, 16, '#FFFDF9', '0.92');
    $s .= '<circle cx="680" cy="150" r="52" fill="none" stroke="' . $p['warm'] . '" stroke-width="5" opacity="0.9"/>';
    // cây + đèn
    $s .= plant(80, 440, $p);
    $s .= rr(120, 180, 10, 60, 4, $p['wood']);
    $s .= poly([106, 180, 144, 180, 138, 158, 112, 158], $p['warm']);
    return $s;
}

function sceneBalcony(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 360, 0, 'url(#sky)');
    $s .= poly([0, 300, 90, 240, 130, 280, 210, 210, 260, 260, 340, 180, 400, 230, 470, 160, 530, 220, 600, 150, 660, 210, 800, 140, 800, 360, 0, 360], $p['deep'], '0.45');
    $s .= poly([0, 330, 110, 290, 200, 320, 300, 270, 420, 315, 520, 260, 640, 310, 800, 250, 800, 360, 0, 360], $p['deep'], '0.7');
    $s .= rr(40, 90, 240, 300, 12, $p['wall']);
    $s .= rr(52, 102, 216, 276, 8, 'url(#sky)');
    $s .= '<line x1="160" y1="102" x2="160" y2="378" stroke="' . $p['wall'] . '" stroke-width="8"/>';
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.48');
    $s .= rr(0, 424, 800, 10, 0, $p['wood'], '0.65');
    $s .= rr(0, 380, 800, 10, 4, $p['deep']);
    for ($x = 20; $x < 800; $x += 46) { $s .= rr($x, 388, 8, 46, 3, $p['deep']); }
    $s .= '<path d="M60 300 Q140 340 240 300 Q340 340 440 300 Q540 340 640 300 Q700 320 760 300" fill="none" stroke="' . $p['deep'] . '" stroke-width="3"/>';
    $s .= circ(140, 322, 5, $p['warm']); $s .= circ(340, 322, 5, $p['warm']); $s .= circ(540, 322, 5, $p['warm']);
    $s .= rr(520, 470, 110, 12, 6, $p['wood']);
    $s .= rr(540, 482, 10, 44, 4, $p['wood']);
    $s .= rr(600, 482, 10, 44, 4, $p['wood']);
    $s .= circ(585, 464, 7, $p['warm']);
    $s .= rr(660, 380, 54, 106, 14, $p['accent']);
    $s .= rr(668, 470, 38, 16, 6, $p['deep']);
    $s .= plant(300, 450, $p);
    $s .= plant(420, 460, $p);
    return $s;
}

function sceneBalconyLake(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    // hồ hoàng hôn
    $s .= rr(0, 0, 800, 330, 0, 'url(#sky)');
    $s .= circ(560, 140, 52, $p['warm'], '0.9');
    $s .= rr(0, 260, 800, 70, 0, $p['accent'], '0.55');
    $s .= rr(0, 300, 800, 8, 0, $p['warm'], '0.4');
    $s .= rr(0, 330, 800, 4, 0, $p['warm'], '0.3');
    $s .= poly([0, 260, 120, 200, 200, 250, 300, 180, 420, 250, 540, 190, 660, 250, 800, 200, 800, 262, 0, 262], $p['deep'], '0.5');
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.48');
    $s .= rr(0, 424, 800, 10, 0, $p['wood'], '0.65');
    $s .= rr(0, 382, 800, 10, 4, $p['deep']);
    for ($x = 20; $x < 800; $x += 46) { $s .= rr($x, 390, 8, 44, 3, $p['deep']); }
    // cửa kính mở
    $s .= rr(40, 90, 230, 290, 12, $p['wall']);
    $s .= rr(52, 102, 206, 266, 8, 'url(#sky)');
    $s .= '<line x1="155" y1="102" x2="155" y2="368" stroke="' . $p['wall'] . '" stroke-width="8"/>';
    // bàn ghế nhỏ + cây
    $s .= rr(500, 480, 100, 12, 6, $p['wood']);
    $s .= rr(520, 492, 9, 40, 4, $p['wood']);
    $s .= rr(572, 492, 9, 40, 4, $p['wood']);
    $s .= circ(550, 474, 7, $p['warm']);
    $s .= plant(360, 460, $p);
    $s .= plant(640, 470, $p);
    $s .= circ(700, 440, 26, $p['accent'], '0.95');
    return $s;
}

function sceneView(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 600, 0, $p['wall'], '0.25');
    $s .= rr(60, 40, 680, 470, 18, $p['wood'], '0.6');
    $s .= rr(76, 56, 648, 438, 12, 'url(#sky)');
    $s .= circ(400, 250, 52, $p['warm'], '0.85');
    $s .= rr(76, 330, 648, 164, 0, $p['accent'], '0.5');
    $s .= rr(76, 352, 648, 8, 0, $p['warm'], '0.4');
    $s .= rr(76, 388, 648, 4, 0, $p['warm'], '0.3');
    $s .= poly([76, 330, 160, 240, 240, 310, 330, 220, 420, 300, 520, 210, 610, 290, 724, 230, 724, 330], $p['deep'], '0.6');
    $s .= '<path d="M180 150 q10 -12 22 0 q12 -12 22 0" fill="none" stroke="' . $p['deep'] . '" stroke-width="3" opacity="0.7"/>';
    $s .= '<path d="M560 130 q10 -12 22 0 q12 -12 22 0" fill="none" stroke="' . $p['deep'] . '" stroke-width="3" opacity="0.7"/>';
    $s .= rr(40, 510, 720, 26, 10, $p['wood']);
    $s .= plant(640, 478, $p);
    return $s;
}

function sceneKitchen(array $p): string
{
    $s = baseRoom($p);
    $s .= rr(40, 60, 260, 140, 12, $p['deep']);
    for ($i = 0; $i < 3; $i++) {
        $s .= rr(52 + $i * 84, 72, 72, 116, 8, $p['deep']);
        $s .= '<line x1="' . (52 + $i * 84) . '" y1="130" x2="' . (124 + $i * 84) . '" y2="130" stroke="' . $p['wall'] . '" stroke-width="3" opacity="0.5"/>';
    }
    $s .= rr(40, 210, 260, 12, 4, $p['wood']);
    $s .= rr(340, 90, 200, 12, 4, $p['wood']);
    $s .= rr(360, 50, 30, 42, 6, $p['accent'], '0.85');
    $s .= rr(406, 40, 26, 52, 6, $p['warm'], '0.85');
    $s .= rr(448, 56, 28, 36, 6, $p['deep'], '0.8');
    $s .= windowSky($p, 590, 60, 160, 190);
    $s .= rr(40, 420, 720, 22, 8, $p['wood']);
    $s .= rr(40, 442, 720, 130, 0, $p['deep'], '0.9');
    $s .= rr(120, 388, 150, 34, 10, '#FFFFFF', '0.9');
    $s .= circ(160, 405, 12, $p['deep'], '0.8'); $s .= circ(230, 405, 12, $p['deep'], '0.8');
    $s .= rr(340, 366, 64, 56, 14, $p['accent']);
    $s .= circ(372, 358, 20, $p['accent']);
    $s .= ell(600, 400, 40, 14, $p['deep'], '0.85');
    $s .= rr(626, 394, 8, 30, 3, $p['wood']);
    $s .= '<line x1="400" y1="0" x2="400" y2="40" stroke="' . $p['deep'] . '" stroke-width="4"/>';
    $s .= poly([360, 40, 440, 40, 424, 84, 376, 84], $p['warm'], '0.9');
    return $s;
}

function sceneWorkspace(array $p): string
{
    $s = baseRoom($p);
    $s .= ell(360, 510, 220, 44, $p['warm'], '0.22');
    $s .= rr(70, 90, 170, 230, 10, $p['deep'], '0.9');
    $s .= rr(86, 110, 138, 8, 3, $p['warm'], '0.8');
    $s .= rr(86, 168, 138, 8, 3, $p['warm'], '0.8');
    $s .= rr(86, 226, 138, 8, 3, $p['warm'], '0.8');
    $s .= rr(94, 122, 16, 42, 3, $p['accent'], '0.9');
    $s .= rr(120, 130, 14, 34, 3, $p['accent'], '0.7');
    $s .= rr(148, 118, 14, 46, 3, $p['warm'], '0.85');
    $s .= rr(100, 180, 18, 42, 3, $p['warm'], '0.85');
    $s .= rr(132, 176, 14, 46, 3, $p['accent'], '0.8');
    $s .= rr(100, 238, 20, 40, 3, $p['accent'], '0.85');
    $s .= rr(140, 234, 14, 44, 3, $p['warm'], '0.8');
    $s .= windowSky($p, 590, 70, 150, 200);
    $s .= rr(240, 360, 380, 16, 6, $p['wood']);
    $s .= rr(258, 376, 14, 60, 4, $p['wood']);
    $s .= rr(588, 376, 14, 60, 4, $p['wood']);
    $s .= rr(330, 316, 130, 10, 4, $p['deep']);
    $s .= rr(340, 268, 110, 50, 6, $p['deep']);
    $s .= rr(346, 274, 98, 38, 4, $p['accent'], '0.95');
    $s .= rr(540, 336, 10, 26, 4, $p['wood']);
    $s .= poly([512, 336, 578, 336, 570, 320, 520, 320], $p['warm']);
    $s .= rr(300, 336, 22, 26, 6, '#FFFFFF', '0.9');
    $s .= rr(420, 396, 70, 16, 8, $p['accent']);
    $s .= rr(432, 412, 10, 52, 4, $p['wood']);
    $s .= rr(468, 412, 10, 52, 4, $p['wood']);
    $s .= rr(438, 320, 34, 80, 12, $p['deep']);
    $s .= plant(80, 470, $p);
    return $s;
}

function sceneDining(array $p): string
{
    $s = baseRoom($p);
    $s .= windowSky($p, 60, 70, 180, 220);
    $s .= ell(430, 500, 230, 48, $p['warm'], '0.22');
    // bàn ăn
    $s .= rr(290, 330, 220, 18, 8, $p['wood']);
    $s .= rr(320, 348, 16, 90, 4, $p['wood']);
    $s .= rr(464, 348, 16, 90, 4, $p['wood']);
    // đèn chùm trên bàn
    $s .= '<line x1="400" y1="0" x2="400" y2="180" stroke="' . $p['deep'] . '" stroke-width="3"/>';
    $s .= poly([368, 180, 432, 180, 418, 216, 382, 216], $p['warm'], '0.95');
    // ghế
    $s .= rr(300, 330, 46, 88, 12, $p['deep']);
    $s .= rr(310, 404, 26, 16, 5, $p['deep']);
    $s .= rr(454, 330, 46, 88, 12, $p['deep']);
    $s .= rr(464, 404, 26, 16, 5, $p['deep']);
    // đĩa + ly
    $s .= ell(400, 322, 22, 7, '#FFFDF9');
    $s .= ell(352, 322, 22, 7, '#FFFDF9');
    $s .= ell(448, 322, 22, 7, '#FFFDF9');
    // sideboard + cây
    $s .= rr(600, 380, 130, 14, 6, $p['wood']);
    $s .= rr(614, 394, 12, 54, 4, $p['wood']);
    $s .= rr(704, 394, 12, 54, 4, $p['wood']);
    $s .= circ(665, 376, 9, $p['accent']);
    $s .= plant(90, 450, $p);
    $s .= plant(560, 460, $p);
    return $s;
}

function sceneExterior(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#sky)');
    $s .= rr(120, 140, 240, 420, 14, $p['accent'], '0.5');
    $s .= rr(320, 70, 320, 490, 14, $p['deep'], '0.95');
    for ($row = 0; $row < 4; $row++) {
        for ($col = 0; $col < 3; $col++) {
            $wx = 348 + $col * 96; $wy = 100 + $row * 100;
            $s .= rr($wx, $wy, 56, 62, 8, $p['warm'], '0.85');
            $s .= '<line x1="' . $wx . '" y1="' . ($wy + 31) . '" x2="' . ($wx + 56) . '" y2="' . ($wy + 31) . '" stroke="' . $p['deep'] . '" stroke-width="3" opacity="0.4"/>';
        }
    }
    $s .= rr(430, 430, 100, 130, 10, $p['warm'], '0.9');
    $s .= rr(520, 470, 60, 90, 8, $p['warm'], '0.75');
    $s .= rr(600, 200, 170, 360, 14, $p['accent'], '0.45');
    $s .= circ(90, 420, 60, $p['accent'], '0.9');
    $s .= circ(60, 470, 46, $p['accent'], '0.85');
    $s .= rr(80, 480, 18, 90, 6, $p['wood']);
    $s .= circ(700, 460, 50, $p['deep'], '0.9');
    $s .= rr(692, 510, 16, 60, 6, $p['wood']);
    $s .= rr(0, 550, 800, 50, 0, $p['wood'], '0.45');
    return $s;
}

function sceneFallback(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 600, 0, $p['wall'], '0.3');
    $s .= rr(300, 210, 200, 180, 24, $p['deep'], '0.9');
    $s .= rr(330, 240, 140, 90, 14, $p['warm'], '0.85');
    $s .= rr(380, 300, 44, 10, 4, $p['warm']);
    $s .= plant(360, 370, $p);
    $s .= circ(400, 180, 46, $p['accent'], '0.9');
    $s .= '<text x="400" y="196" text-anchor="middle" font-family="Georgia, serif" font-size="40" font-weight="700" fill="#FFFDF9">Y</text>';
    return $s;
}

/* ═════════════════════════════════════════════════════════════════════════
   WRAP + WRITE
   ═════════════════════════════════════════════════════════════════════════ */
function wrap(string $body, array $p): string
{
    $defs = '<defs>'
        . '<linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="' . $p['bg1'] . '"/><stop offset="1" stop-color="' . $p['bg2'] . '"/></linearGradient>'
        . '<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="' . $p['sky1'] . '"/><stop offset="0.55" stop-color="' . $p['sky2'] . '"/><stop offset="1" stop-color="' . $p['sky3'] . '"/></linearGradient>'
        . '</defs>';
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600" role="img" aria-label="YangHomestay demo image">'
        . $defs . $body . '</svg>' . "\n";
}

$scenes = [
    'living'           => 'sceneLiving',
    'living-luxury'    => 'sceneLivingLuxury',
    'bedroom'          => 'sceneBedroom',
    'bedroom-luxury'   => 'sceneBedroomLuxury',
    'bathroom'         => 'sceneBathroom',
    'bathroom-luxury'  => 'sceneBathroomLuxury',
    'balcony'          => 'sceneBalcony',
    'balcony-lake'     => 'sceneBalconyLake',
    'view'             => 'sceneView',
    'kitchen'          => 'sceneKitchen',
    'workspace'        => 'sceneWorkspace',
    'dining'           => 'sceneDining',
];

/* ── Map unit → [district, coverScene, galleryScenes] (truthful với amenities) ── */
$unitMap = [
    1  => ['old-quarter', 'balcony',         ['bedroom', 'living', 'bathroom']],
    2  => ['old-quarter', 'living',          ['bedroom', 'workspace', 'kitchen']],
    3  => ['old-quarter', 'bedroom',         ['bathroom-luxury', 'balcony', 'living']],
    4  => ['truc-bach',   'view',            ['balcony-lake', 'bedroom-luxury', 'bathroom-luxury']],
    5  => ['truc-bach',   'balcony',         ['bedroom', 'living', 'bathroom']],
    6  => ['truc-bach',   'living-luxury',   ['dining', 'kitchen', 'bedroom-luxury', 'bathroom']],
    7  => ['west-lake',   'view',            ['balcony-lake', 'bathroom-luxury', 'bedroom-luxury']],
    8  => ['west-lake',   'workspace',       ['living-luxury', 'kitchen', 'bedroom']],
    9  => ['west-lake',   'living-luxury',   ['dining', 'kitchen', 'balcony-lake', 'bedroom-luxury', 'bathroom-luxury']],
    10 => ['dong-da',     'bedroom',         ['kitchen', 'living', 'bathroom']],
    11 => ['dong-da',     'living',          ['workspace', 'kitchen', 'bedroom']],
    12 => ['dong-da',     'living',          ['dining', 'kitchen', 'balcony', 'bedroom']],
    13 => ['cau-giay',    'bedroom',         ['workspace', 'kitchen', 'living']],
    14 => ['cau-giay',    'workspace',       ['living', 'kitchen', 'bedroom']],
    15 => ['cau-giay',    'bedroom',         ['balcony', 'living', 'bathroom']],
    16 => ['cau-giay',    'living-luxury',   ['dining', 'kitchen', 'balcony', 'bedroom']],
];

/* ═════════════════════════════════════════════════════════════════════════
   GENERATE SVG SOURCES
   ═════════════════════════════════════════════════════════════════════════ */
$toRender = []; // dst jpg path => svg path

foreach ($palettes as $district => $pal) {
    $dir = $outRoot . '/' . $district;
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }

    // TẤT CẢ scenes + exterior cho mọi district → mọi path ảnh luôn tồn tại
    foreach ($scenes as $sc => $fnName) {
        $fn = $fnName;
        $svg = $svgSrc . '/' . $district . '-scene-' . $sc . '.svg';
        file_put_contents($svg, wrap($fn($pal), $pal));
        $toRender[$dir . '/scene-' . $sc . '.jpg'] = $svg;
    }
    // exterior scene (dùng cho property gallery: neighborhood/common_area/other)
    $svg = $svgSrc . '/' . $district . '-scene-exterior.svg';
    file_put_contents($svg, wrap(sceneExterior($pal), $pal));
    $toRender[$dir . '/scene-exterior.jpg'] = $svg;

    // unit covers
    foreach ($unitMap as $u => [$d, $cover, $_g]) {
        if ($d !== $district) continue;
        $fn = $scenes[$cover];
        $svg = $svgSrc . '/' . $district . '-unit-' . $u . '-cover.svg';
        file_put_contents($svg, wrap($fn($pal), $pal));
        $toRender[$dir . '/unit-' . $u . '-cover.jpg'] = $svg;
    }

    // property covers
    $svg = $svgSrc . '/' . $district . '-property-cover.svg';
    file_put_contents($svg, wrap(sceneExterior($pal), $pal));
    $toRender[$dir . '/property-cover.jpg'] = $svg;
}

// fallback (dùng palette cau-giay làm nền trung tính)
$fallbackDir = $outRoot . '/fallback';
if (!is_dir($fallbackDir)) { mkdir($fallbackDir, 0777, true); }
$svg = $svgSrc . '/fallback-unit.svg';
file_put_contents($svg, wrap(sceneFallback($palettes['cau-giay']), $palettes['cau-giay']));
$toRender[$fallbackDir . '/unit-fallback.jpg'] = $svg;
$svg = $svgSrc . '/fallback-property.svg';
file_put_contents($svg, wrap(sceneFallback($palettes['dong-da']), $palettes['dong-da']));
$toRender[$fallbackDir . '/property-fallback.jpg'] = $svg;

echo "SVG sources: " . count($toRender) . "\n";

/* ═════════════════════════════════════════════════════════════════════════
   RASTERIZE — Chrome (SVG→PNG) + PowerShell (PNG→JPG q90)
   ═════════════════════════════════════════════════════════════════════════ */
$chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
if (!is_file($chrome)) { $chrome = 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe'; }
if (!is_file($chrome)) { fwrite(STDERR, "Chrome not found — raster skipped, SVGs kept in _src/\n"); exit(1); }

$absRoot = str_replace('\\', '/', $root); // e.g. C:/xampp/htdocs/ks/WEBODER — absolute, Windows
$tmp = $root . '/tools/.raster-tmp';
if (!is_dir($tmp)) { mkdir($tmp, 0777, true); }
$profDir = str_replace('\\', '/', $tmp) . '/chrome-profile'; // cô lập profile → tránh Chrome attach vào instance cũ bỏ qua screenshot

$psLines = ['Add-Type -AssemblyName System.Drawing'];
$psLines[] = '$enc = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq "image/jpeg" }';
foreach ($toRender as $dst => $svg) {
    $absSvg = str_replace('\\', '/', $svg); // đã absolute — chỉ normalize
    $absDst = str_replace('\\', '/', $dst);
    $png    = preg_replace('/\.svg$/i', '.png', $absSvg);
    $psLines[] = '& "' . $chrome . '" --headless --disable-gpu --hide-scrollbars --no-first-run --no-default-browser-check --user-data-dir="' . $profDir . '" --screenshot="' . $png . '" --window-size=800,600 "file:///' . $absSvg . '" 2>$null | Out-Null';
    $psLines[] = 'if (Test-Path "' . $png . '") {';
    $psLines[] = '  $img = [System.Drawing.Image]::FromFile("' . $png . '")';
    $psLines[] = '  $ep = New-Object System.Drawing.Imaging.EncoderParameters(1)';
    $psLines[] = '  $ep.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]90)';
    $psLines[] = '  $img.Save("' . $absDst . '", $enc, $ep)';
    $psLines[] = '  $img.Dispose()';
    $psLines[] = '  Remove-Item "' . $png . '" -Force';
    $psLines[] = '}';
}
$psLines[] = 'Write-Output "RASTER-DONE"';
$psScript = $tmp . '/convert.ps1';
file_put_contents($psScript, implode("\n", $psLines));

$cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File "' . str_replace('\\', '/', $psScript) . '"';
exec($cmd . ' 2>&1', $out, $code);
$done = 0;
foreach ($out as $line) { if (strpos($line, 'RASTER-DONE') !== false) $done = 1; }

$jpgCount = 0;
foreach (glob($outRoot . '/*/*.jpg') as $f) { $jpgCount++; }
foreach (glob($outRoot . '/fallback/*.jpg') as $f) { $jpgCount++; }

echo "Raster: " . ($done ? 'OK' : 'FAILED (code ' . $code . ')') . " — JPG files: $jpgCount\n";
echo "Done. JPGs in assets/images/homestays/{district}/ ; SVG sources kept in _src/\n";
