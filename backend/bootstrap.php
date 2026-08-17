<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * BOOTSTRAP — khởi tạo stack Supabase cho backend YangHomestay
 *
 * Gọi đầu mỗi controller:
 *   require_once __DIR__ . '/bootstrap.php';
 *
 * • Session an toàn + security headers
 * • supabase() — trả SupabaseClient; fail-safe nếu thiếu config
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/lib/Utf8.php';
require_once __DIR__ . '/lib/Session.php';
require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/ApiResponse.php';
require_once __DIR__ . '/lib/Csrf.php';
require_once __DIR__ . '/lib/Validation.php';
require_once __DIR__ . '/lib/SupabaseClient.php';
require_once __DIR__ . '/lib/Pg.php';

// Autoload class còn lại (services + lib) — KHÔNG dùng Composer, chạy trên mọi PHP hosting.
spl_autoload_register(static function (string $class): void {
    foreach ([__DIR__ . '/lib/', __DIR__ . '/services/'] as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

Session::start();
Security::sendHeaders();

// CORS tối thiểu: cùng domain mặc định. Nếu có APP_URL khác origin (dev),
// set env APP_URL để cho phép origin đó.
$appUrl = getenv('APP_URL') ?: '';
if ($appUrl !== '') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin === rtrim($appUrl, '/')) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Trả SupabaseClient — fail-safe khi thiếu cấu hình.
 * Chỉ trả microcopy thân thiện; chi tiết kỹ thuật được ghi vào server log,
 * tuyệt đối không lộ path/credential ra browser.
 */
function supabase(): SupabaseClient
{
    $client = SupabaseClient::getInstance();
    if ($client === null) {
        Security::logError('bootstrap', 'supabase_config_missing');
        ApiResponse::fail('Dịch vụ dữ liệu tạm thời chưa sẵn sàng. Vui lòng thử lại sau.', 503);
    }
    return $client;
}

/** Đọc body JSON an toàn. */
function jsonBody(): array
{
    Security::assertBodySize();
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Action từ query string. */
function action(): string
{
    return (string)($_GET['action'] ?? '');
}
