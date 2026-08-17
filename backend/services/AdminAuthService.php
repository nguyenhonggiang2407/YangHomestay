<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * ADMIN AUTH SERVICE — admin (super_admin | manager | staff)
 *
 * Session: $_SESSION['admin_id'] + $_SESSION['admin_role']
 * Role luôn đọc từ DB khi check — KHÔNG trust role từ frontend.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class AdminAuthService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    public function login(string $identifier, string $password, string $portal = ''): array
    {
        $identifier = trim($identifier);

        if ($this->tooManyAttempts($identifier)) {
            return ['success' => false, 'message' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.', 'status' => 429];
        }
        if ($identifier === '' || $password === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập tên đăng nhập và mật khẩu', 'status' => 400];
        }

        // username hoặc email
        $res = $this->db->select('admins', [
            'select'  => 'id,username,email,password_hash,full_name,role,status',
            'filters' => 'or=(' . Pg::orEqStr('username', $identifier) . ',' . Pg::orEqStr('email', $identifier) . ')',
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('admin_auth', 'login_lookup_failed');
            return ['success' => false, 'message' => 'Dịch vụ đăng nhập tạm thời không khả dụng. Vui lòng thử lại sau.', 'status' => 503];
        }
        $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;

        if ($row === null || !password_verify($password, (string)($row['password_hash'] ?? ''))) {
            $this->logAttempt($identifier, null, false);
            return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng', 'status' => 401];
        }

        if (($row['status'] ?? '') !== 'active') {
            $this->logAttempt($identifier, (int)$row['id'], false);
            return ['success' => false, 'message' => 'Tài khoản đang bị khóa', 'status' => 403];
        }

        $portal = trim($portal);
        if ($portal !== '' && !InternalAccess::roleCanUsePortal((string)$row['role'], $portal)) {
            return [
                'success' => false,
                'message' => 'Tài khoản này không có quyền truy cập cổng ' . match ($portal) {
                    'admin' => 'Super Admin',
                    'manager' => 'Manager',
                    'staff' => 'Lễ tân',
                    default => 'nội bộ',
                } . '.',
                'status' => 403,
            ];
        }

        Session::regenerate();
        Session::set('admin_id', (int)$row['id']);
        Session::set('admin_role', (string)$row['role']);
        Session::set('admin_name', (string)$row['full_name']);
        Session::set('admin_login_time', time());

        $this->db->update('admins', ['last_login_at' => gmdate('Y-m-d\TH:i:s\Z')], Pg::eqInt('id', (int)$row['id']));
        $this->logAttempt($identifier, (int)$row['id'], true);

        return [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data'    => ['id' => (int)$row['id'], 'username' => $row['username'], 'full_name' => $row['full_name'], 'role' => $row['role']],
            'status'  => 200,
        ];
    }

    public function logout(): array
    {
        Session::remove('admin_id');
        Session::remove('admin_role');
        Session::remove('admin_name');
        Session::remove('admin_login_time');
        return ['success' => true, 'message' => 'Đã đăng xuất', 'status' => 200];
    }

    /**
     * Check auth + verify DB status active (không tin session một mình).
     */
    public function checkAuth(string $portal = ''): array
    {
        $id = Session::get('admin_id');
        if ($id === null) {
            return ['success' => true, 'authenticated' => false, 'data' => null, 'status' => 200];
        }

        $res = $this->db->select('admins', [
            'select'  => 'id,username,email,full_name,role,status',
            'filters' => Pg::eqInt('id', (int)$id),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            // Lỗi database tạm thời không được tự động xóa phiên quản trị.
            Security::logError('admin_auth', 'check_auth_lookup_failed', ['status' => $res['status'] ?? 0]);
            return [
                'success' => false,
                'authenticated' => false,
                'data' => null,
                'message' => 'Dịch vụ xác thực nội bộ tạm thời chưa sẵn sàng.',
                'status' => 503,
            ];
        }
        $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;

        if ($row === null || ($row['status'] ?? '') !== 'active') {
            $this->logout();
            return ['success' => true, 'authenticated' => false, 'data' => null, 'status' => 200];
        }
        // đồng bộ role mới nhất từ DB
        Session::set('admin_role', (string)$row['role']);
        Session::set('admin_name', (string)$row['full_name']);

        $portal = trim($portal);
        $authorized = $portal === '' ? true : InternalAccess::roleCanUsePortal((string)$row['role'], $portal);
        return [
            'success' => true,
            'authenticated' => true,
            'authorized' => $authorized,
            'redirect_portal' => $authorized ? null : InternalAccess::primaryPortalForRole((string)$row['role']),
            'data' => $row,
            'status' => 200,
        ];
    }

    /** Bắt buộc admin đăng nhập → 401 nếu không. */
    public static function requireAdmin(): void
    {
        $id = Session::get('admin_id');
        if ($id === null) {
            ApiResponse::fail('Vui lòng đăng nhập', 401);
        }
        // verify DB status active mỗi lần truy cập quan trọng
        $svc = new self();
        $auth = $svc->checkAuth();
        if (!($auth['success'] ?? false)) {
            ApiResponse::fail((string)($auth['message'] ?? 'Dịch vụ xác thực nội bộ tạm thời chưa sẵn sàng.'), (int)($auth['status'] ?? 503));
        }
        if (empty($auth['authenticated'])) {
            ApiResponse::fail('Phiên đăng nhập không hợp lệ', 401);
        }
    }

    /** Bắt buộc role ∈ danh sách → 403 nếu không. */
    public static function requireRole(array $roles): void
    {
        self::requireAdmin();
        $role = (string)Session::get('admin_role', '');
        if (!in_array($role, $roles, true)) {
            ApiResponse::fail('Bạn không có quyền thực hiện thao tác này', 403);
        }
    }

    public static function currentRole(): string
    {
        return (string)Session::get('admin_role', '');
    }

    public static function currentId(): int
    {
        return (int)Session::get('admin_id', 0);
    }

    private function tooManyAttempts(string $identifier): bool
    {
        $since = Pg::isoTime(time() - 900);
        $filters = [
            'actor_type=eq.admin',
            'success=eq.false',
            'created_at=gte.' . $since,
            'or=(' . Pg::orEqStr('email_or_username', $identifier) . ',' . Pg::orEqStr('ip_address', Security::clientIp()) . ')',
        ];
        $res = $this->db->select('login_logs', ['select' => 'id', 'filters' => $filters, 'count' => true]);
        return $res['ok'] && $res['count'] !== null && $res['count'] >= 8;
    }

    private function logAttempt(string $identifier, ?int $actorId, bool $success): void
    {
        $this->db->insert('login_logs', [
            'actor_type'        => 'admin',
            'actor_id'          => $actorId,
            'email_or_username' => $identifier,
            'success'           => $success,
            'ip_address'        => Security::clientIp(),
            'user_agent'        => Security::userAgent(),
            'created_at'        => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
