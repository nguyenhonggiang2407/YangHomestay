<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/** Super-admin account administration and internal audit log queries. */
final class InternalAdminService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    public function accounts(): array
    {
        $res = $this->db->select('admins', [
            'select' => 'id,username,email,full_name,role,status,last_login_at,created_at,updated_at',
            'order'  => 'id.asc',
            'limit'  => 100,
        ]);
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Không tải được danh sách nhân sự', 'status' => 503];
        }
        return ['success' => true, 'data' => ['items' => $res['data'] ?? []], 'status' => 200];
    }

    public function saveAccount(array $data, int $actorId): array
    {
        $id       = (int)($data['id'] ?? 0);
        $username = trim((string)($data['username'] ?? ''));
        $email    = trim(mb_strtolower((string)($data['email'] ?? '')));
        $fullName = trim((string)($data['full_name'] ?? ''));
        $role     = Pg::whitelist((string)($data['role'] ?? ''), InternalAccess::ROLES);
        $status   = Pg::whitelist((string)($data['status'] ?? 'active'), ['active', 'inactive']);
        $password = (string)($data['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) $errors['username'] = 'Username phải dài 3-50 ký tự và chỉ gồm chữ, số, . _ -';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $errors['email'] = 'Email không hợp lệ';
        if ($fullName === '' || mb_strlen($fullName) > 100) $errors['full_name'] = 'Họ tên không hợp lệ';
        if ($role === null) $errors['role'] = 'Role không hợp lệ';
        if ($status === null) $errors['status'] = 'Trạng thái không hợp lệ';
        if ($id === 0 && mb_strlen($password) < 8) $errors['password'] = 'Mật khẩu mới phải có ít nhất 8 ký tự';
        if ($password !== '' && mb_strlen($password) < 8) $errors['password'] = 'Mật khẩu mới phải có ít nhất 8 ký tự';
        if ($errors) return ['success' => false, 'message' => 'Vui lòng kiểm tra dữ liệu', 'errors' => $errors, 'status' => 422];

        $existing = null;
        if ($id > 0) {
            $r = $this->db->select('admins', [
                'select' => 'id,username,email,full_name,role,status',
                'filters'=> Pg::eqInt('id', $id),
                'limit'  => 1,
            ]);
            $existing = ($r['ok'] && isset($r['data'][0])) ? $r['data'][0] : null;
            if ($existing === null) return ['success' => false, 'message' => 'Không tìm thấy tài khoản', 'status' => 404];
            if ($id === $actorId && ($role !== 'super_admin' || $status !== 'active')) {
                return ['success' => false, 'message' => 'Không thể tự hạ quyền hoặc khóa tài khoản Super Admin đang đăng nhập', 'status' => 409];
            }
        }

        $duplicateFilters = 'or=(' . Pg::orEqStr('username', $username) . ',' . Pg::orEqStr('email', $email) . ')';
        $dup = $this->db->select('admins', ['select' => 'id', 'filters' => $duplicateFilters, 'limit' => 10]);
        if ($dup['ok']) {
            foreach (($dup['data'] ?? []) as $row) {
                if ((int)$row['id'] !== $id) {
                    return ['success' => false, 'message' => 'Username hoặc email đã được sử dụng', 'status' => 409];
                }
            }
        }

        $payload = [
            'username'   => $username,
            'email'      => $email,
            'full_name'  => $fullName,
            'role'       => $role,
            'status'     => $status,
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        if ($password !== '') $payload['password_hash'] = password_hash($password, PASSWORD_BCRYPT);

        if ($id > 0) {
            $res = $this->db->update('admins', $payload, Pg::eqInt('id', $id));
            if (!$res['ok']) return ['success' => false, 'message' => 'Không cập nhật được tài khoản', 'status' => 503];
            $this->audit($actorId, 'UPDATE_INTERNAL_ACCOUNT', 'admins', $id, $existing ?? [], array_diff_key($payload, ['password_hash' => true]));
            return ['success' => true, 'message' => 'Đã cập nhật tài khoản', 'status' => 200];
        }

        $payload['created_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $res = $this->db->insert('admins', $payload, ['return' => 'representation']);
        if (!$res['ok']) return ['success' => false, 'message' => 'Không tạo được tài khoản', 'status' => 503];
        $newId = (int)($res['data'][0]['id'] ?? 0);
        $this->audit($actorId, 'CREATE_INTERNAL_ACCOUNT', 'admins', $newId, [], array_diff_key($payload, ['password_hash' => true]));
        return ['success' => true, 'message' => 'Đã tạo tài khoản nội bộ', 'status' => 201];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        $status = Pg::whitelist($status, ['active', 'inactive']);
        if ($id <= 0 || $status === null) return ['success' => false, 'message' => 'Dữ liệu không hợp lệ', 'status' => 422];
        if ($id === $actorId && $status !== 'active') return ['success' => false, 'message' => 'Không thể tự khóa tài khoản đang đăng nhập', 'status' => 409];

        $r = $this->db->select('admins', ['select' => 'id,role,status', 'filters' => Pg::eqInt('id', $id), 'limit' => 1]);
        $old = ($r['ok'] && isset($r['data'][0])) ? $r['data'][0] : null;
        if ($old === null) return ['success' => false, 'message' => 'Không tìm thấy tài khoản', 'status' => 404];

        $res = $this->db->update('admins', ['status' => $status, 'updated_at' => gmdate('Y-m-d\TH:i:s\Z')], Pg::eqInt('id', $id));
        if (!$res['ok']) return ['success' => false, 'message' => 'Không cập nhật được trạng thái', 'status' => 503];
        $this->audit($actorId, 'CHANGE_INTERNAL_ACCOUNT_STATUS', 'admins', $id, $old, ['status' => $status]);
        return ['success' => true, 'message' => 'Đã cập nhật trạng thái tài khoản', 'status' => 200];
    }

    public function auditLogs(int $page = 1, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);
        $res = $this->db->select('audit_logs', [
            'select' => 'id,admin_id,action,entity_type,entity_id,old_value_json,new_value_json,ip_address,created_at',
            'order'  => 'created_at.desc',
            'count'  => true,
            'limit'  => $limit,
            'offset' => ($page - 1) * $limit,
        ]);
        if (!$res['ok']) return ['success' => false, 'message' => 'Không tải được audit logs', 'status' => 503];

        $items = $res['data'] ?? [];
        $adminIds = array_values(array_unique(array_filter(array_map('intval', array_column($items, 'admin_id')))));
        $map = [];
        if ($adminIds) {
            $ar = $this->db->select('admins', ['select' => 'id,username,full_name,role', 'filters' => Pg::inInts('id', $adminIds), 'limit' => 100]);
            foreach (($ar['ok'] ? $ar['data'] : []) as $a) $map[(int)$a['id']] = $a;
        }
        foreach ($items as &$item) $item['actor'] = $map[(int)($item['admin_id'] ?? 0)] ?? null;

        $total = $res['count'] ?? count($items);
        return ['success' => true, 'data' => ['items' => $items, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]], 'status' => 200];
    }

    public function settings(): array
    {
        return ['success' => true, 'data' => [
            'session_cookie' => ['http_only' => true, 'same_site' => 'Lax', 'secure_on_https' => true],
            'supabase_secret_location' => 'backend/config.supabase.local.php hoặc biến môi trường server-side',
            'production_notes' => ['Bật HTTPS', 'Đổi mật khẩu demo', 'Không public diagnostics', 'Không commit config.supabase.local.php'],
        ], 'status' => 200];
    }

    private function audit(int $adminId, string $action, string $entityType, int $entityId, array $old, array $new): void
    {
        $this->db->insert('audit_logs', [
            'admin_id'       => $adminId,
            'action'         => $action,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId > 0 ? $entityId : null,
            'old_value_json' => $old,
            'new_value_json' => $new,
            'ip_address'     => Security::clientIp(),
            'created_at'     => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
