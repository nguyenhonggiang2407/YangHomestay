<?php
require_once __DIR__ . '/config.php';

class Auth {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($username, $password) {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập tên đăng nhập và mật khẩu'];
        }

        $stmt = $this->db->prepare('SELECT id, username, password, fullname, role, status FROM users WHERE username = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Lỗi cơ sở dữ liệu'];
        }

        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->logLoginAttempt($username, false, 'Tài khoản không tồn tại');
            return ['success' => false, 'message' => 'Tài khoản không tồn tại'];
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        // Kiểm tra mật khẩu (Sử dụng password_verify cho độ bảo mật cao)
        if (!password_verify($password, $user['password'])) {
            $this->logLoginAttempt($username, false, 'Mật khẩu không chính xác');
            return ['success' => false, 'message' => 'Mật khẩu không chính xác'];
        }

        if ($user['status'] !== 'active') {
            $this->logLoginAttempt($username, false, 'Tài khoản bị khóa');
            return ['success' => false, 'message' => 'Tài khoản đang bị khóa'];
        }

        // Chống session fixation: tạo session id mới sau khi đăng nhập
        session_regenerate_id(true);

        // Tạo Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

        // Cập nhật last_login
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();
        }

        $this->logLoginAttempt($username, true, 'Đăng nhập thành công');

        return [
            'success' => true,
            'message' => 'Thành công',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role' => $user['role']
            ]
        ];
    }

    /**
     * Kiểm tra đã đăng nhập chưa
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Lấy user hiện tại (KHÔNG trả password)
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, username, fullname, email, phone, role, status FROM users WHERE id = ?');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user;
    }

    /**
     * Kiểm tra quyền (admin là quyền cao nhất, được phép mọi thao tác)
     */
    public function hasRole($roles) {
        $role = $_SESSION['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if (is_array($roles)) {
            return in_array($role, $roles);
        }
        return $role === $roles;
    }

    /**
     * Đăng xuất: xóa session an toàn
     */
    public function logout() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }

        session_destroy();
        return ['success' => true, 'message' => 'Đã đăng xuất'];
    }

    /**
     * Kiểm tra hết hạn session (SESSION_TIMEOUT)
     */
    public function checkSessionTimeout() {
        if (!isset($_SESSION['login_time'])) {
            return;
        }
        if ((time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
            $this->logout();
        }
    }

    /**
     * Ghi log lần đăng nhập
     */
    private function logLoginAttempt($username, $success, $message) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $successValue = $success ? 1 : 0;

        $stmt = $this->db->prepare(
            'INSERT INTO login_logs (username, success, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('sisss', $username, $successValue, $message, $ip, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $auth = new Auth();
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($auth->login($data['username'] ?? '', $data['password'] ?? ''));
    exit;
}