<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * AUTH SERVICE — khách hàng (PHP Session + password_hash + Supabase)
 *
 * Session source of truth: $_SESSION['customer_id']
 * KHÔNG dựa vào localStorage. KHÔNG trả password_hash ra browser.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class AuthService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    /* ────────────────────────────────────────────────────────────────
     * REGISTER
     * ──────────────────────────────────────────────────────────────── */
    public function register(array $data): array
    {
        try {
            $fullName = Validation::string('full_name', $data['full_name'] ?? '', 100, true, 'họ tên');
            $email    = Validation::email('email', $data['email'] ?? '');
            $phone    = Validation::phoneVN('phone', $data['phone'] ?? '');
            $password = Validation::password('password', $data['password'] ?? '', 8);
            $confirm  = (string)($data['confirm_password'] ?? '');
            if (!hash_equals($password, $confirm)) {
                throw new ValidationException('Mật khẩu xác nhận không khớp.', ['confirm_password' => 'Mật khẩu xác nhận không khớp.']);
            }
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        // Duplicate email. Không tiếp tục đăng ký nếu data source đang lỗi:
        // nếu không, người dùng có thể nhận lỗi khó hiểu hoặc tạo race không cần thiết.
        $dup = $this->db->select('customers', ['select' => 'id', 'filters' => Pg::eqStr('email', $email)]);
        if (!$dup['ok']) {
            Security::logError('auth', 'register_duplicate_check_failed', ['status' => $dup['status'] ?? 0]);
            return ['success' => false, 'message' => 'Dịch vụ đăng ký tạm thời chưa sẵn sàng. Vui lòng thử lại sau.', 'status' => 503];
        }
        if (is_array($dup['data']) && count($dup['data']) > 0) {
            return ['success' => false, 'message' => 'Email đã được sử dụng.', 'errors' => ['email' => 'Email đã được sử dụng.'], 'status' => 409];
        }

        $payload = [
            'full_name'    => $fullName,
            'email'        => $email,
            'phone'        => $phone,
            'password_hash'=> password_hash($password, PASSWORD_BCRYPT),
            'status'       => 'active',
        ];

        $res = $this->db->insert('customers', $payload, ['select' => 'id,full_name,email']);
        if (!$res['ok']) {
            // 23505 = duplicate email (race) → friendly
            if (is_array($res['data']) && ($res['data']['code'] ?? null) === '23505') {
                return ['success' => false, 'message' => 'Email đã được sử dụng.', 'errors' => ['email' => 'Email đã được sử dụng.'], 'status' => 409];
            }
            Security::logError('auth', 'register_failed', ['status' => $res['status']]);
            return ['success' => false, 'message' => 'Lỗi máy chủ, vui lòng thử lại', 'status' => 503];
        }

        $row = is_array($res['data']) && isset($res['data'][0]) ? $res['data'][0] : [];
        return [
            'success' => true,
            'message' => 'Đăng ký thành công. Vui lòng đăng nhập.',
            'data'    => ['id' => $row['id'] ?? null, 'email' => $row['email'] ?? $email],
            'status'  => 201,
        ];
    }

    /* ────────────────────────────────────────────────────────────────
     * LOGIN + THROTTLING
     * ──────────────────────────────────────────────────────────────── */
    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));

        // Basic brute-force throttle: >8 lần fail cùng email/ip trong 15 phút → 429
        if ($this->tooManyAttempts($email)) {
            return ['success' => false, 'message' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.', 'status' => 429];
        }

        if ($email === '' || $password === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập email và mật khẩu', 'status' => 400];
        }

        $res = $this->db->select('customers', [
            'select'  => 'id,full_name,email,phone,password_hash,status,avatar_url',
            'filters' => Pg::eqStr('email', $email),
            'limit'   => 1,
        ]);

        if (!$res['ok']) {
            Security::logError('auth', 'login_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Dịch vụ đăng nhập tạm thời chưa sẵn sàng. Vui lòng thử lại sau.', 'status' => 503];
        }

        $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;

        if ($row === null || !password_verify($password, (string)($row['password_hash'] ?? ''))) {
            $this->logAttempt($email, null, false);
            return ['success' => false, 'message' => 'Email hoặc mật khẩu không đúng', 'status' => 401];
        }

        if (($row['status'] ?? '') !== 'active') {
            $this->logAttempt($email, (int)$row['id'], false);
            return ['success' => false, 'message' => 'Tài khoản đang bị khóa', 'status' => 403];
        }

        // Chống session fixation
        Session::regenerate();
        Session::set('customer_id', (int)$row['id']);
        Session::set('customer_email', (string)$row['email']);
        Session::set('customer_name', (string)$row['full_name']);
        Session::set('customer_login_time', time());

        // last_login_at
        $this->db->update('customers', ['last_login_at' => gmdate('Y-m-d\TH:i:s\Z')], Pg::eqInt('id', (int)$row['id']));

        $this->logAttempt($email, (int)$row['id'], true);

        return [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data'    => self::safeCustomer($row),
            'status'  => 200,
        ];
    }

    public function logout(): array
    {
        // Chỉ xóa context customer — không đụng admin session
        Session::remove('customer_id');
        Session::remove('customer_email');
        Session::remove('customer_name');
        Session::remove('customer_login_time');
        return ['success' => true, 'message' => 'Đã đăng xuất', 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * CHECK AUTH / PROFILE
     * ──────────────────────────────────────────────────────────────── */
    public function checkAuth(): array
    {
        $id = Session::get('customer_id');
        if ($id === null) {
            return ['success' => true, 'authenticated' => false, 'data' => null, 'status' => 200];
        }

        $res = $this->db->select('customers', [
            'select'  => 'id,full_name,email,phone,avatar_url,status',
            'filters' => Pg::eqInt('id', (int)$id),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            // Không xóa session chỉ vì lỗi mạng/database tạm thời.
            Security::logError('auth', 'check_auth_lookup_failed', ['status' => $res['status'] ?? 0]);
            return [
                'success' => false,
                'authenticated' => false,
                'data' => null,
                'message' => 'Dịch vụ xác thực tạm thời chưa sẵn sàng.',
                'status' => 503,
            ];
        }

        $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;

        if ($row === null || ($row['status'] ?? '') !== 'active') {
            $this->logout();
            return ['success' => true, 'authenticated' => false, 'data' => null, 'status' => 200];
        }

        return ['success' => true, 'authenticated' => true, 'data' => self::safeCustomer($row), 'status' => 200];
    }

    /**
     * Bắt buộc customer đăng nhập VÀ còn active ở server.
     * Không chỉ tin vào session id: tài khoản bị khóa/xóa phải mất quyền ngay,
     * tránh session cũ tiếp tục gọi API protected.
     */
    public static function requireCustomer(): void
    {
        $id = Session::get('customer_id');
        if ($id === null) {
            ApiResponse::fail('Vui lòng đăng nhập', 401);
        }

        $auth = (new self())->checkAuth();
        if (!($auth['success'] ?? false)) {
            ApiResponse::fail((string)($auth['message'] ?? 'Dịch vụ xác thực tạm thời chưa sẵn sàng.'), (int)($auth['status'] ?? 503));
        }
        if (empty($auth['authenticated'])) {
            ApiResponse::fail('Phiên đăng nhập không còn hợp lệ. Vui lòng đăng nhập lại.', 401);
        }
    }

    public static function currentId(): int
    {
        return (int)Session::get('customer_id', 0);
    }

    public function getProfile(): array
    {
        $res = $this->db->select('customers', [
            'select'  => 'id,full_name,email,phone,avatar_url,status,created_at,last_login_at',
            'filters' => Pg::eqInt('id', self::currentId()),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('auth', 'profile_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể tải hồ sơ lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($row === null) {
            return ['success' => false, 'message' => 'Không tìm thấy tài khoản', 'status' => 404];
        }
        return ['success' => true, 'data' => $row, 'status' => 200];
    }

    public function updateProfile(array $data): array
    {
        try {
            $fullName = Validation::string('full_name', $data['full_name'] ?? '', 100, true, 'họ tên');
            $phone    = Validation::phoneVN('phone', $data['phone'] ?? '');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $payload = ['full_name' => $fullName, 'phone' => $phone];
        $avatar  = trim((string)($data['avatar_url'] ?? ''));
        if ($avatar !== '' && filter_var($avatar, FILTER_VALIDATE_URL) && strlen($avatar) <= 255) {
            $payload['avatar_url'] = $avatar;
        }

        $res = $this->db->update('customers', $payload, Pg::eqInt('id', self::currentId()));
        if (!$res['ok']) {
            Security::logError('auth', 'profile_update_failed', ['status' => $res['status']]);
            return ['success' => false, 'message' => 'Lỗi khi cập nhật hồ sơ', 'status' => 503];
        }
        Session::set('customer_name', $fullName);
        return ['success' => true, 'message' => 'Cập nhật hồ sơ thành công', 'status' => 200];
    }

    public function changePassword(array $data): array
    {
        try {
            $old = (string)($data['old_password'] ?? '');
            $new = Validation::password('new_password', $data['new_password'] ?? '', 8);
            if (!hash_equals($new, (string)($data['confirm_password'] ?? ''))) {
                throw new ValidationException('Mật khẩu xác nhận không khớp.', ['confirm_password' => 'Mật khẩu xác nhận không khớp.']);
            }
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        $res = $this->db->select('customers', [
            'select'  => 'id,password_hash',
            'filters' => Pg::eqInt('id', self::currentId()),
            'limit'   => 1,
        ]);
        if (!$res['ok']) {
            Security::logError('auth', 'password_lookup_failed', ['status' => $res['status'] ?? 0]);
            return ['success' => false, 'message' => 'Chưa thể xác minh mật khẩu lúc này. Vui lòng thử lại.', 'status' => 503];
        }
        $row = (is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($row === null || !password_verify($old, (string)($row['password_hash'] ?? ''))) {
            return ['success' => false, 'message' => 'Mật khẩu cũ không đúng', 'status' => 400];
        }

        $upd = $this->db->update('customers', ['password_hash' => password_hash($new, PASSWORD_BCRYPT)], Pg::eqInt('id', self::currentId()));
        if (!$upd['ok']) {
            Security::logError('auth', 'password_change_failed', ['status' => $upd['status']]);
            return ['success' => false, 'message' => 'Lỗi khi đổi mật khẩu', 'status' => 503];
        }
        return ['success' => true, 'message' => 'Đổi mật khẩu thành công', 'status' => 200];
    }

    /* ────────────────────────────────────────────────────────────────
     * HELPERS
     * ──────────────────────────────────────────────────────────────── */
    private static function safeCustomer(array $row): array
    {
        unset($row['password_hash']);
        return $row;
    }

    private function tooManyAttempts(string $email): bool
    {
        $since = Pg::isoTime(time() - 900); // 15 phút
        $filters = [
            'actor_type=eq.customer',
            'success=eq.false',
            'created_at=gte.' . $since,
            'or=(' . Pg::orEqStr('email_or_username', $email) . ',' . Pg::orEqStr('ip_address', Security::clientIp()) . ')',
        ];
        $res = $this->db->select('login_logs', ['select' => 'id', 'filters' => $filters, 'count' => true]);
        if ($res['ok'] && $res['count'] !== null && $res['count'] >= 8) {
            Security::logError('auth', 'login_throttled', ['email' => $email]);
            return true;
        }
        return false;
    }

    private function logAttempt(string $email, ?int $actorId, bool $success): void
    {
        $this->db->insert('login_logs', [
            'actor_type'        => 'customer',
            'actor_id'          => $actorId,
            'email_or_username' => $email,
            'success'           => $success,
            'ip_address'        => Security::clientIp(),
            'user_agent'        => Security::userAgent(),
            'created_at'        => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
