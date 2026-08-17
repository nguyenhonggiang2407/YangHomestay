<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * ADMIN API — YangHomestay (admin panel)
 *
 * Kiến trúc: Browser → PHP (router) → Services → Supabase REST/RPC → PostgreSQL
 *
 * • Router CHỈ làm routing — business logic trong backend/services/
 * • Mọi action (trừ login/check-auth) yêu cầu admin session (requireAdmin),
 *   role kiểm tra qua requireRole (KHÔNG trust role frontend).
 * • POST/PATCH/DELETE cần CSRF token (X-CSRF-Token header).
 * • State machine booking enforced ở AdminBookingService.
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
    foreach (['authenticated', 'authorized', 'redirect_portal'] as $key) {
        if (array_key_exists($key, $r)) {
            $payload[$key] = $r[$key];
        }
    }
    if (!empty($r['errors'])) {
        $payload['errors'] = $r['errors'];
    }
    ApiResponse::send($payload, (int)($r['status'] ?? 200));
}

/* ── CSRF: mọi state-changing request, kể cả login ── */
$stateChanging = in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true);
if ($stateChanging && !Csrf::verifyRequest()) {
    ApiResponse::fail('Phiên đăng nhập đã hết hạn, vui lòng thử lại', 419);
}

// Central server-side RBAC. Existing requireAdmin() calls remain as defense in depth.
InternalAccess::guardApiAction((string)$action);

/* ── Router ── */
try {
    switch ($action) {

        /* ══════════ AUTH (public) ══════════ */
        case 'get-csrf':
            respond(['success' => true, 'data' => ['token' => Csrf::token()], 'status' => 200]);
            break;

        case 'login':
            if ($method !== 'POST') { ApiResponse::fail('Method not allowed', 405); }
            $d = jsonBody();
            respond((new AdminAuthService())->login((string)($d['identifier'] ?? $d['username'] ?? ''), (string)($d['password'] ?? ''), (string)($d['portal'] ?? '')));
            break;

        case 'logout':
            AdminAuthService::requireAdmin();
            respond((new AdminAuthService())->logout());
            break;

        case 'check-auth':
            respond((new AdminAuthService())->checkAuth((string)($_GET['portal'] ?? '')));
            break;

        /* ══════════ DASHBOARD / CALENDAR / REVENUE ══════════ */
        case 'dashboard':
            AdminAuthService::requireAdmin();
            respond((new AdminBookingService())->dashboard(AdminAuthService::currentRole()));
            break;

        case 'calendar':
            AdminAuthService::requireAdmin();
            $start = (string)($_GET['start'] ?? date('Y-m-d', strtotime('-14 days')));
            $end   = (string)($_GET['end'] ?? date('Y-m-d', strtotime('+45 days')));
            respond((new AdminBookingService())->calendar($start, $end));
            break;

        case 'revenue':
            AdminAuthService::requireAdmin();
            respond((new AdminBookingService())->revenueReport());
            break;

        /* ══════════ BOOKINGS (admin) ══════════ */
        case 'admin-bookings':
            AdminAuthService::requireAdmin();
            $f = [
                'status'         => (string)($_GET['status'] ?? ''),
                'payment_status' => (string)($_GET['payment_status'] ?? ''),
                'search'         => (string)($_GET['search'] ?? ''),
            ];
            respond((new AdminBookingService())->list($f, (int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 20)));
            break;

        case 'admin-booking-detail':
            AdminAuthService::requireAdmin();
            respond((new AdminBookingService())->detail((int)($_GET['id'] ?? 0)));
            break;

        case 'admin-booking-status':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminBookingService())->updateStatus((int)($d['id'] ?? 0), (string)($d['status'] ?? ''), AdminAuthService::currentId()));
            break;

        case 'admin-booking-payment':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminBookingService())->updatePaymentStatus((int)($d['id'] ?? 0), (string)($d['payment_status'] ?? ''), AdminAuthService::currentId()));
            break;

        /* ══════════ CUSTOMERS (admin) ══════════ */
        case 'admin-customers':
            AdminAuthService::requireAdmin();
            $f = [
                'status' => (string)($_GET['status'] ?? ''),
                'search' => (string)($_GET['search'] ?? ''),
            ];
            respond((new AdminCustomerService())->list($f, (int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 20)));
            break;

        /* ══════════ REVIEWS (admin) ══════════ */
        case 'admin-reviews':
            AdminAuthService::requireAdmin();
            respond((new ReviewService())->adminList((string)($_GET['status'] ?? null)));
            break;

        case 'admin-review-moderate':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new ReviewService())->moderate((int)($d['id'] ?? 0), (string)($d['status'] ?? ''), AdminAuthService::currentId()));
            break;

        /* ══════════ CONTACT MESSAGES (admin) ══════════ */
        case 'admin-contacts':
            AdminAuthService::requireAdmin();
            respond((new ContactService())->adminList((string)($_GET['status'] ?? null)));
            break;

        case 'admin-contact-status':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new ContactService())->adminUpdateStatus((int)($d['id'] ?? 0), (string)($d['status'] ?? '')));
            break;

        /* ══════════ PROPERTIES (admin CRUD) ══════════ */
        case 'admin-properties':
            AdminAuthService::requireAdmin();
            respond((new AdminCatalogService())->propertyList((int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 20)));
            break;

        case 'admin-property-get':
            AdminAuthService::requireAdmin();
            respond((new AdminCatalogService())->propertyGet((int)($_GET['id'] ?? 0)));
            break;

        case 'admin-property-save':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->propertySave($d, AdminAuthService::currentId(), !empty($d['id'])));
            break;

        case 'admin-property-status':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->propertySetStatus((int)($d['id'] ?? 0), (string)($d['status'] ?? ''), AdminAuthService::currentId()));
            break;

        /* ══════════ UNITS (admin CRUD) ══════════ */
        case 'admin-units':
            AdminAuthService::requireAdmin();
            respond((new AdminCatalogService())->unitList((int)($_GET['property_id'] ?? 0), (int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 50)));
            break;

        case 'admin-unit-get':
            AdminAuthService::requireAdmin();
            respond((new AdminCatalogService())->unitGet((int)($_GET['id'] ?? 0)));
            break;

        case 'admin-unit-save':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->unitSave($d, AdminAuthService::currentId(), !empty($d['id'])));
            break;

        case 'admin-unit-status':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->unitSetStatus((int)($d['id'] ?? 0), (string)($d['status'] ?? ''), AdminAuthService::currentId()));
            break;

        /* ══════════ RATE RULES (admin) ══════════ */
        case 'admin-rate-rules':
            AdminAuthService::requireAdmin();
            respond((new AdminCatalogService())->rateRuleList(isset($_GET['unit_id']) && $_GET['unit_id'] !== '' ? (int)$_GET['unit_id'] : null));
            break;

        case 'admin-rate-rule-save':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->rateRuleSave($d, AdminAuthService::currentId(), !empty($d['id'])));
            break;

        case 'admin-rate-rule-delete':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->rateRuleDelete((int)($d['id'] ?? 0), AdminAuthService::currentId()));
            break;

        /* ══════════ PROMOTIONS (admin) ══════════ */
        case 'admin-promotions':
            AdminAuthService::requireAdmin();
            respond((new AdminCatalogService())->promotionList());
            break;

        case 'admin-promotion-save':
            AdminAuthService::requireAdmin();
            $d = jsonBody();
            respond((new AdminCatalogService())->promotionSave($d, AdminAuthService::currentId(), !empty($d['id'])));
            break;


        case 'operations-today':
            AdminAuthService::requireAdmin();
            respond((new AdminBookingService())->todayOperations());
            break;

        case 'internal-accounts':
            respond((new InternalAdminService())->accounts());
            break;

        case 'internal-account-save':
            $d = jsonBody();
            respond((new InternalAdminService())->saveAccount($d, AdminAuthService::currentId()));
            break;

        case 'internal-account-status':
            $d = jsonBody();
            respond((new InternalAdminService())->setStatus((int)($d['id'] ?? 0), (string)($d['status'] ?? ''), AdminAuthService::currentId()));
            break;

        case 'audit-logs':
            respond((new InternalAdminService())->auditLogs((int)($_GET['page'] ?? 1), (int)($_GET['limit'] ?? 50)));
            break;

        case 'system-settings':
            respond((new InternalAdminService())->settings());
            break;

        /* ══════════ DEFAULT ══════════ */
        default:
            ApiResponse::fail('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    Security::logError('admin_api', 'unhandled', ['action' => $action]);
    ApiResponse::fail('Lỗi máy chủ, vui lòng thử lại', 500);
}
