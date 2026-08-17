<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * SUPABASE HEALTH CHECK — DEVELOPMENT ONLY
 *
 * Kiểm tra:
 *   PHP / cURL / HTTPS / Supabase URL / Supabase REST / Database / Properties
 *
 * • Development + web:  mở trực tiếp được.
 * • Production + web:   YÊU CẦU admin session — nếu không → 404.
 * • CLI:                chạy được (php supabase-health.php).
 *
 * TUYỆT ĐỐI KHÔNG hiển thị: secret key, authorization header, DB password.
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../lib/Session.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/ApiResponse.php';
require_once __DIR__ . '/../lib/SupabaseClient.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    Security::sendHeaders();
    Session::start();
    $env = SupabaseClient::config()['environment'] ?? 'development';

    // Production: chỉ admin session được xem diagnostic
    if ($env === 'production' && !Session::has('admin_id')) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
}

$lines = [];

/* 1. PHP version */
$lines['PHP'] = version_compare(PHP_VERSION, '8.0.0', '>=') ? 'OK (' . PHP_VERSION . ')' : 'FAIL (cần PHP 8+)';

/* 2. cURL extension */
$lines['cURL'] = extension_loaded('curl') ? 'OK' : 'FAIL (thiếu extension curl)';

/* 3. Config */
if (!SupabaseClient::isConfigured()) {
    $lines['Supabase URL'] = 'CONFIG MISSING';
    $lines['Supabase REST'] = 'SKIPPED';
    $lines['Database'] = 'SKIPPED';
    $lines['Properties'] = 'SKIPPED';

    if (!$isCli) {
        header('HTTP/1.1 503 Service Unavailable');
    }
    foreach ($lines as $k => $v) {
        echo str_pad($k, 16) . $v . PHP_EOL;
    }
    echo PHP_EOL . 'SUPABASE CONFIGURATION REQUIRED — điền SUPABASE_URL + SUPABASE_SECRET_KEY vào backend/config.supabase.local.php (hoặc env vars).' . PHP_EOL;
    exit($isCli ? 1 : 0);
}

$client = SupabaseClient::getInstance();

/* 4. HTTPS */
$cfg = SupabaseClient::config();
$lines['Supabase URL'] = str_starts_with((string)$cfg['url'], 'https://') ? 'OK' : 'FAIL (phải là HTTPS)';

/* 5. REST reachable + Database + Properties */
if ($client !== null) {
    // Request nhỏ: lấy 1 dòng properties kèm count=exact → kiểm tra REST + DB + bảng
    $res = $client->select('properties', [
        'select' => 'id,name,status',
        'limit'  => 1,
        'count'  => true,
    ]);

    if ($res['ok']) {
        $lines['Supabase REST'] = 'OK';
        $lines['Database'] = 'OK';
        $total = $res['count'];
        if ($total === null) {
            $total = is_array($res['data']) ? count($res['data']) : 0;
        }
        $lines['Properties'] = (string)$total;
    } else {
        $lines['Supabase REST'] = 'FAIL (HTTP ' . $res['status'] . ')';
        $lines['Database'] = 'FAIL';
        $lines['Properties'] = 'FAIL';
    }
} else {
    $lines['Supabase REST'] = 'SKIPPED';
    $lines['Database'] = 'SKIPPED';
    $lines['Properties'] = 'SKIPPED';
}

if (!$isCli && str_starts_with($lines['Supabase REST'] ?? 'OK', 'FAIL')) {
    header('HTTP/1.1 503 Service Unavailable');
}

foreach ($lines as $k => $v) {
    echo str_pad($k, 16) . $v . PHP_EOL;
}

if (isset($res) && !$res['ok']) {
    echo PHP_EOL . 'Chi tiết lỗi chỉ ghi trong logs/error.log (không hiển thị raw response).' . PHP_EOL;
}
