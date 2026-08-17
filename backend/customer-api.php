<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * CUSTOMER API — YangHomestay (public + khách hàng)
 *
 * Kiến trúc: Browser → PHP (router) → Services → Supabase REST/RPC → PostgreSQL
 *
 * • Router CHỈ làm routing/controller — business logic nằm trong backend/services/
 * • GET công khai không cần đăng nhập; mọi POST/PATCH/DELETE cần CSRF token.
 *   Login/register lấy token trước qua get-csrf để chống login CSRF.
 * • Mọi response dùng ApiResponse (JSON thống nhất), KHÔNG lộ lỗi Supabase raw.
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/* ── Helper: chuyển kết quả service → JSON response ── */
function respond(array $r): void
{
    $payload = [
        'success' => (bool)($r['success'] ?? false),
        'message' => (string)($r['message'] ?? 'OK'),
    ];
    if (array_key_exists('data', $r)) {
        $payload['data'] = $r['data'];
    }
    if (array_key_exists('authenticated', $r)) {
        $payload['authenticated'] = $r['authenticated'];
    }
    if (!empty($r['errors'])) {
        $payload['errors'] = $r['errors'];
    }
    ApiResponse::send($payload, (int)($r['status'] ?? 200));
}

/* ── CSRF: mọi state-changing request, kể cả login/register ── */
$stateChanging = in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true);
if ($stateChanging && !Csrf::verifyRequest()) {
    ApiResponse::fail('Phiên đăng nhập đã hết hạn, vui lòng thử lại', 419);
}

/* ── Router ── */
try {
    switch ($action) {

        /* ══════════ AUTH (công khai) ══════════ */
        case 'get-csrf':
            respond(['success' => true, 'data' => ['token' => Csrf::token()], 'status' => 200]);
            break;

        case 'register':
            if ($method !== 'POST') { ApiResponse::fail('Method not allowed', 405); }
            respond((new AuthService())->register(jsonBody()));
            break;

        case 'login':
            if ($method !== 'POST') { ApiResponse::fail('Method not allowed', 405); }
            $d = jsonBody();
            respond((new AuthService())->login((string)($d['email'] ?? ''), (string)($d['password'] ?? '')));
            break;

        case 'logout':
            if ($method !== 'POST') { ApiResponse::fail('Method not allowed', 405); }
            respond((new AuthService())->logout());
            break;

        case 'check-auth':
            respond((new AuthService())->checkAuth());
            break;

        /* ══════════ PROPERTY / UNIT (công khai) ══════════ */
        case 'properties':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            $f = [
                'district' => (string)($_GET['district'] ?? ''),
                'featured' => (string)($_GET['featured'] ?? ''),
                'search'   => (string)($_GET['search'] ?? ''),
                'sort'     => (string)($_GET['sort'] ?? ''),
            ];
            respond((new PropertyService())->list($f, (int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 12)));
            break;

        case 'property-detail':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            respond((new PropertyService())->detail((int)($_GET['id'] ?? 0)));
            break;

        case 'units':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            $f = [
                'property'  => (string)($_GET['property'] ?? ''),
                'type'      => (string)($_GET['type'] ?? ''),
                'min_price' => (string)($_GET['min_price'] ?? ''),
                'max_price' => (string)($_GET['max_price'] ?? ''),
                'guests'    => (string)($_GET['guests'] ?? ''),
                'district'  => (string)($_GET['district'] ?? ''),
                'featured'  => (string)($_GET['featured'] ?? ''),
                'search'    => (string)($_GET['search'] ?? ''),
                'tag'       => (string)($_GET['tag'] ?? ''),
                'sort'      => (string)($_GET['sort'] ?? ''),
                'check_in'  => (string)($_GET['check_in'] ?? ''),
                'check_out' => (string)($_GET['check_out'] ?? ''),
            ];
            respond((new UnitService())->list($f, (int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 12)));
            break;

        case 'unit-detail':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            respond((new UnitService())->detail((int)($_GET['id'] ?? 0)));
            break;

        case 'units-by-property':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            $unitService = new UnitService();
            $items = $unitService->listByProperty((int)($_GET['property_id'] ?? 0));
            if ($unitService->lastError() !== null) {
                respond(['success' => false, 'message' => $unitService->lastError(), 'status' => 503]);
            }
            respond(['success' => true, 'data' => $items, 'status' => 200]);
            break;

        /* ══════════ AVAILABILITY / PRICING (công khai) ══════════ */
        case 'availability':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            respond((new AvailabilityService())->check(
                (int)($_GET['unit_id'] ?? 0),
                (string)($_GET['check_in'] ?? ''),
                (string)($_GET['check_out'] ?? '')
            ));
            break;

        case 'booking-quote':
            if ($method !== 'GET') { ApiResponse::fail('Method not allowed', 405); }
            $promo = trim((string)($_GET['promotion_code'] ?? ''));
            respond((new PricingService())->quote(
                (int)($_GET['unit_id'] ?? 0),
                (string)($_GET['check_in'] ?? ''),
                (string)($_GET['check_out'] ?? ''),
                $promo !== '' ? $promo : null,
                isset($_GET['guests']) && $_GET['guests'] !== '' ? (int)$_GET['guests'] : null
            ));
            break;

        /* ══════════ YANGMATCH (công khai, deterministic) ══════════ */
        case 'yangmatch':
            if ($method !== 'POST') { ApiResponse::fail('Method not allowed', 405); }
            respond((new YangMatchService())->match(jsonBody()));
            break;

        /* ══════════ CONTACT (công khai) ══════════ */
        case 'contact':
            if ($method !== 'POST') { ApiResponse::fail('Method not allowed', 405); }
            respond((new ContactService())->submit(jsonBody()));
            break;

        /* ══════════ PROFILE (customer) ══════════ */
        case 'get-profile':
            AuthService::requireCustomer();
            respond((new AuthService())->getProfile());
            break;

        case 'update-profile':
            AuthService::requireCustomer();
            respond((new AuthService())->updateProfile(jsonBody()));
            break;

        case 'change-password':
            AuthService::requireCustomer();
            respond((new AuthService())->changePassword(jsonBody()));
            break;

        /* ══════════ BOOKING (customer) ══════════ */
        case 'create-booking':
            AuthService::requireCustomer();
            respond((new BookingService())->create(AuthService::currentId(), jsonBody()));
            break;

        case 'my-bookings':
            AuthService::requireCustomer();
            respond((new BookingService())->myBookings(AuthService::currentId(), (string)($_GET['status'] ?? null)));
            break;

        case 'booking-detail':
            AuthService::requireCustomer();
            respond((new BookingService())->detail(AuthService::currentId(), (int)($_GET['id'] ?? 0)));
            break;

        case 'cancel-booking':
            AuthService::requireCustomer();
            $d = jsonBody();
            respond((new BookingService())->cancel(AuthService::currentId(), (int)($d['id'] ?? 0), (string)($d['reason'] ?? '')));
            break;

        /* ══════════ FAVORITES (customer) ══════════ */
        case 'favorites':
            AuthService::requireCustomer();
            respond((new FavoriteService())->list(AuthService::currentId()));
            break;

        case 'add-favorite':
            AuthService::requireCustomer();
            $d = jsonBody();
            respond((new FavoriteService())->add(AuthService::currentId(), (int)($d['unit_id'] ?? 0)));
            break;

        case 'remove-favorite':
            AuthService::requireCustomer();
            $d = jsonBody();
            respond((new FavoriteService())->remove(AuthService::currentId(), (int)($d['unit_id'] ?? 0)));
            break;

        /* ══════════ REVIEW (customer) ══════════ */
        case 'create-review':
            AuthService::requireCustomer();
            respond((new ReviewService())->create(AuthService::currentId(), jsonBody()));
            break;

        /* ══════════ DEFAULT ══════════ */
        default:
            ApiResponse::fail('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    Security::logError('customer_api', 'unhandled', ['action' => $action]);
    ApiResponse::fail('Lỗi máy chủ, vui lòng thử lại', 500);
}
