<?php
/**
 * ═════════════════════════════════════════════════════════════════════════
 * YANGHOMESTAY — Bổ sung ảnh cho các vị trí còn trống:
 *   1. hero-hanoi.jpg            (nền hero trang chủ, 1600x700)
 *   2. district-{slug}.jpg       (5 thẻ khu vực — skyline theo palette quận)
 *   3. nearby-{cat}.jpg          (5 ảnh theo loại địa điểm quanh homestay)
 *   4. story-hanoi.jpg           (ảnh câu chuyện thương hiệu)
 * Sinh: SVG → headless Chrome → PNG → PowerShell System.Drawing → JPG q90
 * Chạy:  php tools/generate-extra-images.php
 * ═════════════════════════════════════════════════════════════════════════
 */

$root = realpath(__DIR__ . '/..');
$outRoot = $root . '/assets/images/homestays';
$svgSrc = $outRoot . '/_src';
if (!is_dir($svgSrc)) { mkdir($svgSrc, 0777, true); }

/* ── Palettes (giống main generator) ── */
$palettes = [
    'old-quarter' => ['bg1' => '#F5EBDD', 'bg2' => '#EBDCC4', 'wall' => '#F0E2CC', 'wood' => '#9A6B43', 'accent' => '#B96F4A', 'deep' => '#6E4428', 'warm' => '#D9A441', 'sky1' => '#F6DEB0', 'sky2' => '#E9BE7E', 'sky3' => '#D3935C'],
    'truc-bach'   => ['bg1' => '#F2EEE3', 'bg2' => '#E2E2CE', 'wall' => '#E9E7D4', 'wood' => '#8E7A52', 'accent' => '#5E7B6B', 'deep' => '#3F5A4E', 'warm' => '#C2A25E', 'sky1' => '#EEDFB4', 'sky2' => '#DCC898', 'sky3' => '#BFA475'],
    'west-lake'   => ['bg1' => '#F7EDDF', 'bg2' => '#EBD9BE', 'wall' => '#F1E3CC', 'wood' => '#A0714B', 'accent' => '#C0754F', 'deep' => '#7A4528', 'warm' => '#D9A441', 'sky1' => '#F8DCA6', 'sky2' => '#EFA96F', 'sky3' => '#D97B52'],
    'dong-da'     => ['bg1' => '#F1EDE2', 'bg2' => '#DFDFC9', 'wall' => '#E8E5D2', 'wood' => '#8A744E', 'accent' => '#6E7A52', 'deep' => '#41492F', 'warm' => '#C09A54', 'sky1' => '#EDDBB0', 'sky2' => '#D9BC85', 'sky3' => '#B79A6C'],
    'cau-giay'    => ['bg1' => '#F2F0EA', 'bg2' => '#E0E0D6', 'wall' => '#E9E8DE', 'wood' => '#9A8A66', 'accent' => '#5F7D78', 'deep' => '#3B5552', 'warm' => '#C3A25C', 'sky1' => '#EFDFB6', 'sky2' => '#DCC393', 'sky3' => '#BCA174'],
];

function rr(float $x, float $y, float $w, float $h, float $r, string $fill, string $opacity = '1'): string
{
    return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . $r . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function circ(float $cx, float $cy, float $r, string $fill, string $opacity = '1'): string
{
    return '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function poly(array $pts, string $fill, string $opacity = '1'): string
{
    return '<polygon points="' . implode(',', $pts) . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function ell(float $cx, float $cy, float $rx, float $ry, string $fill, string $opacity = '1'): string
{
    return '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="' . $fill . '" opacity="' . $opacity . '"/>';
}
function plant(float $x, float $y, array $p): string
{
    $s = '<rect x="' . $x . '" y="' . $y . '" width="34" height="26" rx="6" fill="' . $p['deep'] . '"/>';
    $s .= '<ellipse cx="' . ($x + 8) . '" cy="' . ($y - 14) . '" rx="9" ry="15" fill="' . $p['accent'] . '" transform="rotate(-24 ' . ($x + 8) . ' ' . ($y - 14) . ')"/>';
    $s .= '<ellipse cx="' . ($x + 20) . '" cy="' . ($y - 20) . '" rx="9" ry="16" fill="' . $p['accent'] . '" transform="rotate(8 ' . ($x + 20) . ' ' . ($y - 20) . ')"/>';
    $s .= '<ellipse cx="' . ($x + 28) . '" cy="' . ($y - 10) . '" rx="7" ry="12" fill="' . $p['deep'] . '" transform="rotate(28 ' . ($x + 28) . ' ' . ($y - 10) . ')"/>';
    return $s;
}
function wrap(string $body, array $p, int $w = 800, int $h = 600): string
{
    $defs = '<defs>'
        . '<linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="' . $p['bg1'] . '"/><stop offset="1" stop-color="' . $p['bg2'] . '"/></linearGradient>'
        . '<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="' . $p['sky1'] . '"/><stop offset="0.55" stop-color="' . $p['sky2'] . '"/><stop offset="1" stop-color="' . $p['sky3'] . '"/></linearGradient>'
        . '</defs>';
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="YangHomestay demo image">'
        . $defs . $body . '</svg>' . "\n";
}

/* ── HERO — skyline Hà Nội rộng (1600x700), để dưới overlay tối ── */
function sceneHero(array $p): string
{
    $s = rr(0, 0, 1600, 700, 0, 'url(#sky)');
    // mặt trời ấm
    $s .= circ(1230, 210, 70, '#FFF3D6', '0.9');
    $s .= circ(1230, 210, 52, $p['warm'], '0.85');
    // lớp nhà xa (đồi mờ)
    $s .= poly([0, 420, 180, 330, 360, 410, 540, 340, 720, 415, 900, 355, 1080, 420, 1260, 350, 1440, 410, 1600, 370, 1600, 700, 0, 700], $p['deep'], '0.35');
    // dãy phố cổ — nhà ống mái ngói
    $bld = [
        [60, 300, 150, 400], [230, 340, 120, 360], [370, 280, 160, 420], [550, 320, 130, 380],
        [700, 260, 150, 440], [870, 330, 120, 370], [1010, 290, 160, 410], [1190, 340, 130, 360],
        [1340, 300, 140, 400], [1500, 350, 100, 350],
    ];
    foreach ($bld as $i => [$bx, $by, $bw, $bh]) {
        $fill = ($i % 3 === 0) ? $p['accent'] : (($i % 3 === 1) ? $p['deep'] : $p['wood']);
        $s .= rr($bx, $by, $bw, $bh, 6, $fill, '0.92');
        for ($row = 0; $row < 4; $row++) {
            for ($col = 0; $col < 3; $col++) {
                $s .= rr($bx + 14 + $col * ($bw - 40) / 3, $by + 24 + $row * 90, 34, 50, 4, $p['warm'], '0.8');
            }
        }
        // mái ngói đỏ sẫm
        $s .= poly([$bx - 8, $by, $bx + $bw / 2, $by - 34, $bx + $bw + 8, $by], '#7A4A2E', '0.85');
    }
    // cây xanh
    $s .= plant(40, 430, $p); $s .= plant(880, 430, $p); $s .= plant(1560, 450, $p);
    // mặt nước hồ phía dưới
    $s .= rr(0, 560, 1600, 140, 0, $p['deep'], '0.35');
    for ($i = 0; $i < 8; $i++) {
        $s .= '<line x1="' . ($i * 200 + 20) . '" y1="' . (590 + ($i % 3) * 22) . '" x2="' . ($i * 200 + 150) . '" y2="' . (600 + ($i % 3) * 22) . '" stroke="' . $p['warm'] . '" stroke-width="4" opacity="0.35"/>';
    }
    return $s;
}

/* ── DISTRICT — phố nhà ống + nét đặc trưng (biến thể theo quận) ── */
function sceneDistrict(array $p, int $variant): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#sky)');
    $s .= circ(($variant % 2 === 0) ? 620 : 180, 130, 46, '#FFF3D6', '0.85');
    $s .= poly([0, 360, 200, 280, 400, 350, 600, 290, 800, 340, 800, 600, 0, 600], $p['deep'], '0.3');
    // nhà ống 2 tầng
    foreach ([40, 220, 400, 580] as $i => $bx) {
        $s .= rr($bx, 220, 170, 380, 8, ($i % 2 === 0) ? $p['accent'] : $p['deep'], '0.9');
        for ($row = 0; $row < 2; $row++) {
            for ($col = 0; $col < 3; $col++) {
                $s .= rr($bx + 16 + $col * 52, 250 + $row * 150, 32, 90, 4, $p['warm'], '0.85');
            }
        }
        $s .= poly([$bx - 6, 220, $bx + 85, 186, $bx + 176, 220], '#7A4A2E', '0.85');
        $s .= rr($bx + 60, 560, 50, 40, 4, $p['wood']);
    }
    $s .= plant(20, 460, $p); $s .= plant(760, 470, $p);
    // đặc trưng quận: variant 1 = hồ (view), 2 = cây xanh, 3 = tòa cao ốc, 4 = chợ/ẩm thực
    if ($variant === 1) {
        $s .= ell(660, 520, 140, 46, $p['deep'], '0.5'); // hồ
        $s .= '<line x1="560" y1="520" x2="700" y2="520" stroke="' . $p['warm'] . '" stroke-width="5" opacity="0.5"/>';
    } elseif ($variant === 2) {
        $s .= plant(140, 430, $p); $s .= plant(200, 420, $p); $s .= plant(520, 430, $p);
    } elseif ($variant === 3) {
        $s .= rr(610, 140, 160, 300, 8, $p['deep'], '0.85'); // cao ốc
        for ($row = 0; $row < 5; $row++) for ($col = 0; $col < 2; $col++) {
            $s .= rr(626 + $col * 64, 160 + $row * 54, 40, 34, 3, $p['warm'], '0.8');
        }
    } else {
        // chợ — mái che + hàng quán
        $s .= poly([140, 300, 220, 240, 300, 300], '#B96F4A', '0.9');
        $s .= rr(150, 300, 140, 60, 6, $p['warm'], '0.85');
        $s .= rr(160, 310, 30, 40, 4, $p['deep'], '0.8');
    }
    return $s;
}

/* ── NEARBY — theo category ── */
function sceneFood(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 420, 0, $p['wall']);
    $s .= rr(0, 420, 800, 180, 0, $p['wood'], '0.3');
    $s .= poly([0, 140, 400, 60, 800, 140, 800, 200, 0, 200], $p['deep'], '0.75');
    $s .= rr(150, 220, 500, 60, 10, $p['warm'], '0.9');
    for ($i = 0; $i < 4; $i++) $s .= rr(200 + $i * 110, 260, 70, 90, 6, $p['deep'], '0.85');
    $s .= circ(235, 305, 16, $p['accent']); $s .= circ(345, 305, 16, $p['accent']);
    $s .= rr(300, 360, 200, 60, 10, $p['accent'], '0.85'); // bàn ăn
    $s .= plant(80, 380, $p); $s .= plant(700, 380, $p);
    return $s;
}
function sceneCoffee(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 430, 0, $p['wall']);
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.32');
    $s .= rr(60, 70, 220, 250, 10, $p['wood'], '0.85'); // cửa sổ
    $s .= rr(70, 80, 200, 230, 8, $p['sky1']);
    $s .= circ(620, 130, 34, $p['warm'], '0.8');
    $s .= rr(260, 300, 280, 70, 12, $p['wood']); // quầy
    $s .= circ(310, 318, 18, '#FFFFFF', '0.9'); $s .= circ(350, 318, 18, '#FFFFFF', '0.9');
    $s .= rr(360, 300, 40, 70, 8, $p['deep']); // máy pha
    $s .= rr(460, 290, 60, 80, 10, $p['deep'], '0.9'); // kệ sách
    for ($i = 0; $i < 3; $i++) $s .= rr(468, 300 + $i * 22, 44, 12, 3, $p['accent'], '0.6');
    $s .= plant(80, 400, $p); $s .= plant(700, 400, $p);
    return $s;
}
function sceneAttraction(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#sky)');
    $s .= circ(140, 130, 40, '#FFF3D6', '0.85');
    $s .= poly([0, 340, 200, 260, 400, 330, 600, 270, 800, 330, 800, 600, 0, 600], $p['deep'], '0.3');
    // cổng đình/chùa
    $s .= poly([200, 340, 400, 200, 600, 340], $p['accent'], '0.95');
    $s .= poly([260, 300, 400, 210, 540, 300], $p['deep'], '0.9');
    $s .= rr(300, 340, 200, 130, 10, $p['wood'], '0.95');
    $s .= circ(400, 390, 26, $p['warm']);
    $s .= poly([330, 300, 400, 250, 470, 300], $p['warm'], '0.9'); // mái ngói
    $s .= rr(330, 470, 140, 60, 8, $p['deep'], '0.5'); // bậc
    $s .= plant(120, 400, $p); $s .= plant(660, 410, $p); $s .= plant(720, 400, $p);
    return $s;
}
function sceneNightlife(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, '#2B2620');
    $s .= circ(120, 100, 40, $p['warm'], '0.5');
    $s .= circ(680, 120, 30, $p['warm'], '0.4');
    // nhà đêm với đèn
    foreach ([60, 240, 420, 600] as $i => $bx) {
        $s .= rr($bx, 260, 150, 340, 8, $p['deep'], '0.95');
        for ($row = 0; $row < 3; $row++) for ($col = 0; $col < 2; $col++) {
            $s .= rr($bx + 22 + $col * 60, 290 + $row * 96, 36, 60, 4, $p['warm'], '0.85');
        }
        $s .= poly([$bx - 6, 260, $bx + 75, 226, $bx + 156, 260], '#7A4A2E', '0.9');
    }
    // đèn lồng
    for ($i = 0; $i < 6; $i++) {
        $x = 60 + $i * 130;
        $s .= '<line x1="' . $x . '" y1="120" x2="' . $x . '" y2="170" stroke="#6B6257" stroke-width="3"/>';
        $s .= circ($x, 185, 17, ($i % 2 === 0) ? '#C0392B' : '#E8A33D', '0.95');
    }
    $s .= rr(0, 560, 800, 40, 0, '#1C1813');
    return $s;
}
function scenePhotography(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#sky)');
    $s .= rr(0, 0, 800, 480, 0, $p['wall']);
    $s .= rr(0, 480, 800, 120, 0, $p['deep'], '0.4');
    // view rộng — hồ + núi
    $s .= poly([0, 300, 200, 200, 400, 280, 600, 210, 800, 290, 800, 480, 0, 480], $p['deep'], '0.5');
    $s .= ell(400, 500, 340, 60, $p['deep'], '0.45');
    for ($i = 0; $i < 5; $i++) {
        $s .= '<line x1="' . (100 + $i * 150) . '" y1="500" x2="' . (200 + $i * 150) . '" y2="500" stroke="' . $p['warm'] . '" stroke-width="4" opacity="0.4"/>';
    }
    $s .= circ(660, 150, 44, '#FFF3D6', '0.9');
    // khung máy ảnh (góc)
    $s .= rr(560, 60, 120, 70, 10, $p['deep'], '0.9');
    $s .= circ(620, 95, 20, $p['warm']);
    $s .= plant(40, 420, $p); $s .= plant(740, 430, $p);
    return $s;
}
function sceneStory(array $p): string
{
    $s = rr(0, 0, 800, 600, 0, 'url(#bg)');
    $s .= rr(0, 0, 800, 430, 0, $p['wall']);
    $s .= rr(0, 430, 800, 170, 0, $p['wood'], '0.3');
    $s .= rr(60, 80, 260, 240, 12, $p['deep'], '0.9'); // cửa sổ lớn
    $s .= rr(70, 90, 240, 220, 10, $p['sky1']);
    $s .= circ(180, 200, 34, $p['warm'], '0.9');
    $s .= rr(380, 140, 220, 130, 14, $p['warm'], '0.5'); // tranh treo
    $s .= rr(400, 160, 70, 90, 6, $p['accent'], '0.8');
    $s .= rr(500, 160, 70, 90, 6, $p['deep'], '0.8');
    $s .= rr(300, 320, 300, 100, 18, $p['deep']); // sofa
    $s .= rr(320, 300, 120, 34, 14, $p['accent']);
    $s .= rr(460, 300, 120, 34, 14, $p['accent']);
    $s .= rr(340, 290, 44, 30, 8, $p['warm'], '0.9'); // gối
    $s .= plant(90, 400, $p); $s .= plant(700, 400, $p);
    $s .= rr(620, 420, 100, 14, 7, $p['wood']); // bàn trà
    $s .= circ(660, 408, 12, $p['warm']);
    return $s;
}

/* ═══════════════ BUILD LIST ═══════════════ */
$toRender = [];

// 1. HERO — dùng palette west-lake (hoàng hôn ấm)
$svg = $svgSrc . '/hero-hanoi.svg';
file_put_contents($svg, wrap(sceneHero($palettes['west-lake']), $palettes['west-lake'], 1600, 700));
$toRender[$outRoot . '/hero-hanoi.jpg'] = $svg;

// 2. DISTRICT cards (5)
$districtSlugs = ['old-quarter', 'truc-bach', 'west-lake', 'dong-da', 'cau-giay'];
foreach ($districtSlugs as $i => $slug) {
    $svg = $svgSrc . '/district-' . $slug . '.svg';
    file_put_contents($svg, wrap(sceneDistrict($palettes[$slug], $i + 1), $palettes[$slug]));
    $toRender[$outRoot . '/district-' . $slug . '.jpg'] = $svg;
}

// 3. STORY
$svg = $svgSrc . '/story-hanoi.svg';
file_put_contents($svg, wrap(sceneStory($palettes['truc-bach']), $palettes['truc-bach']));
$toRender[$outRoot . '/story-hanoi.jpg'] = $svg;

// 4. NEARBY categories (5)
$nearbyMap = [
    'food'         => 'sceneFood',
    'coffee'       => 'sceneCoffee',
    'attraction'   => 'sceneAttraction',
    'nightlife'    => 'sceneNightlife',
    'photography'  => 'scenePhotography',
];
$nearbyDir = $outRoot . '/nearby';
if (!is_dir($nearbyDir)) { mkdir($nearbyDir, 0777, true); }
foreach ($nearbyMap as $cat => $fn) {
    $pal = $palettes['old-quarter']; // nền vintage chung cho địa điểm
    $svg = $svgSrc . '/nearby-' . $cat . '.svg';
    file_put_contents($svg, wrap($fn($pal), $pal));
    $toRender[$nearbyDir . '/nearby-' . $cat . '.jpg'] = $svg;
}

echo "Extra SVG sources: " . count($toRender) . "\n";

/* ═══════════════ RASTERIZE ═══════════════ */
$chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
if (!is_file($chrome)) { $chrome = 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe'; }
if (!is_file($chrome)) { fwrite(STDERR, "Chrome not found — raster skipped\n"); exit(1); }

$absRoot = str_replace('\\', '/', $root);
$tmp = $root . '/tools/.raster-tmp';
if (!is_dir($tmp)) { mkdir($tmp, 0777, true); }
$profDir = str_replace('\\', '/', $tmp) . '/chrome-profile';

$psLines = ['Add-Type -AssemblyName System.Drawing'];
$psLines[] = '$enc = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq "image/jpeg" }';
foreach ($toRender as $dst => $svg) {
    $absSvg = str_replace('\\', '/', $svg);
    $absDst = str_replace('\\', '/', $dst);
    $w = (strpos($absSvg, 'hero-hanoi') !== false) ? 1600 : 800;
    $h = (strpos($absSvg, 'hero-hanoi') !== false) ? 700 : 600;
    $png = preg_replace('/\.svg$/i', '.png', $absSvg);
    $psLines[] = '& "' . $chrome . '" --headless --disable-gpu --hide-scrollbars --no-first-run --no-default-browser-check --user-data-dir="' . $profDir . '" --screenshot="' . $png . '" --window-size=' . $w . ',' . $h . ' "file:///' . $absSvg . '" 2>$null | Out-Null';
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
$psScript = $tmp . '/convert-extra.ps1';
file_put_contents($psScript, implode("\n", $psLines));

$cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File "' . str_replace('\\', '/', $psScript) . '"';
exec($cmd . ' 2>&1', $out, $code);
$done = 0;
foreach ($out as $line) { if (strpos($line, 'RASTER-DONE') !== false) $done = 1; }

$jpgCount = 0;
foreach (glob($outRoot . '/hero-hanoi.jpg') as $f) $jpgCount++;
foreach (glob($outRoot . '/district-*.jpg') as $f) $jpgCount++;
foreach (glob($outRoot . '/story-hanoi.jpg') as $f) $jpgCount++;
foreach (glob($outRoot . '/nearby/*.jpg') as $f) $jpgCount++;

echo "Raster: " . ($done ? 'OK' : 'FAILED (code ' . $code . ')') . " — JPG files: $jpgCount\n";
echo "Done.\n";
