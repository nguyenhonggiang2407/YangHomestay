<?php
/**
 * ═════════════════════════════════════════════════════════════════════════
 * CUSTOMER AUTHENTICATION
 * Xử lý đăng nhập, đăng ký, xác thực khách hàng
 * ═════════════════════════════════════════════════════════════════════════
 */
 
require_once __DIR__ . '/config.php';
 
class CustomerAuth {
    private $db;
 
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
 
    /**
     * Register new customer
     */
    public function register($data) {
        // Validate input
        if (empty($data['fullname']) || empty($data['email']) || empty($data['password']) || 
            empty($data['phone']) || empty($data['idcard']) || empty($data['date_of_birth'])) {
            return ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
        }
 
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ'];
        }
 
        // Validate phone
        if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', $data['phone'])) {
            return ['success' => false, 'message' => 'Số điện thoại không hợp lệ'];
        }
 
        // Validate password length
        if (strlen($data['password']) < 6) {
            return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự'];
        }
 
        // Check if email exists
        $email = $this->db->real_escape_string($data['email']);
        $stmt = $this->db->prepare('SELECT id FROM customer_users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Email đã được đăng ký'];
        }
        $stmt->close();
 
        // Check if ID card exists
        $idcard = $this->db->real_escape_string($data['idcard']);
        $stmt = $this->db->prepare('SELECT id FROM customer_users WHERE idcard = ?');
        $stmt->bind_param('s', $idcard);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'CCCD/Passport đã được đăng ký'];
        }
        $stmt->close();
 
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
 
        // Insert customer
        $stmt = $this->db->prepare(
            'INSERT INTO customer_users (fullname, idcard, phone, email, password, date_of_birth, nationality, address, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
 
        if (!$stmt) {
            return ['success' => false, 'message' => 'Lỗi cơ sở dữ liệu'];
        }
 
        $fullname = $this->db->real_escape_string($data['fullname']);
        $phone = $this->db->real_escape_string($data['phone']);
        $dob = $this->db->real_escape_string($data['date_of_birth']);
        $nationality = $this->db->real_escape_string($data['nationality'] ?? 'Việt Nam');
        $address = $this->db->real_escape_string($data['address'] ?? '');
        $status = 'active';
 
        $stmt->bind_param(
            'sssssssss',
            $fullname,
            $idcard,
            $phone,
            $email,
            $hashedPassword,
            $dob,
            $nationality,
            $address,
            $status
        );
 
        if ($stmt->execute()) {
            $userId = $this->db->insert_id;
            $stmt->close();
            $this->logActivity($userId, 'register', 'Đăng ký tài khoản');
            return ['success' => true, 'message' => 'Đăng ký thành công', 'userId' => $userId];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => 'Lỗi khi đăng ký'];
        }
    }
 
    /**
     * Login customer
     */
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Vui lòng nhập email và mật khẩu'];
        }
 
        $email = $this->db->real_escape_string(trim($email));
 
        $stmt = $this->db->prepare(
            'SELECT id, fullname, email, phone, idcard, nationality, password, status FROM customer_users WHERE email = ?'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Lỗi cơ sở dữ liệu'];
        }
 
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
 
        if ($result->num_rows === 0) {
            $stmt->close();
            $this->logLoginAttempt($email, false, 'Email không tồn tại');
            return ['success' => false, 'message' => 'Email hoặc mật khẩu không đúng'];
        }
 
        $user = $result->fetch_assoc();
        $stmt->close();
 
        // Check if account is active
        if ($user['status'] !== 'active') {
            $this->logLoginAttempt($email, false, 'Tài khoản bị vô hiệu hóa');
            return ['success' => false, 'message' => 'Tài khoản đã bị khóa'];
        }
 
        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->logLoginAttempt($email, false, 'Mật khẩu không đúng');
            return ['success' => false, 'message' => 'Email hoặc mật khẩu không đúng'];
        }
 
        // Chống session fixation: tạo session id mới sau khi đăng nhập
        session_regenerate_id(true);

        // Create session
        $_SESSION['customer_id'] = $user['id'];
        $_SESSION['customer_email'] = $user['email'];
        $_SESSION['customer_fullname'] = $user['fullname'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
 
        // Update last login
        $stmt = $this->db->prepare('UPDATE customer_users SET last_login = NOW() WHERE id = ?');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->close();
 
        $this->logLoginAttempt($email, true, 'Đăng nhập thành công');
        $this->logActivity($user['id'], 'login', 'Đăng nhập');
 
        return [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'fullname' => $user['fullname'],
                'phone' => $user['phone'],
                'idcard' => $user['idcard'],
                'nationality' => $user['nationality']
            ]
        ];
    }
 
    /**
     * Logout customer
     */
    public function logout() {
        if (isset($_SESSION['customer_id'])) {
            $customerId = $_SESSION['customer_id'];
            $this->logActivity($customerId, 'logout', 'Đăng xuất');
        }
        session_destroy();
        return ['success' => true, 'message' => 'Đã đăng xuất'];
    }
 
    /**
     * Check if customer is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
    }
 
    /**
     * Get current customer
     */
    public function getCurrentCustomer() {
        if (!$this->isLoggedIn()) {
            return null;
        }
 
        $stmt = $this->db->prepare('SELECT * FROM customer_users WHERE id = ?');
        $stmt->bind_param('i', $_SESSION['customer_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();
        $stmt->close();

        // KHÔNG trả password hash ra client
        if (is_array($customer)) {
            unset($customer['password']);
        }

        return $customer;
    }
 
    /**
     * Log login attempts
     */
    private function logLoginAttempt($email, $success, $message) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $successValue = $success === true ? 1 : ($success === false ? 0 : NULL);
 
        $stmt = $this->db->prepare(
            'INSERT INTO login_logs (username, success, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('sisss', $email, $successValue, $message, $ip, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
    }
 
    /**
     * Log customer activity
     */
    private function logActivity($customerId, $action, $description) {
        if (!$this->isLoggedIn()) {
            return;
        }
 
        $ip = $_SERVER['REMOTE_ADDR'];
        $userId = $_SESSION['customer_id'];
 
        $stmt = $this->db->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, description, ip_address) VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $entity = 'customer';
            $stmt->bind_param('issss', $userId, $action, $entity, $description, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
 
    /**
     * Change password
     */
    public function changePassword($customerId, $oldPassword, $newPassword) {
        if (empty($oldPassword) || empty($newPassword)) {
            return ['success' => false, 'message' => 'Vui lòng nhập mật khẩu'];
        }
 
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự'];
        }
 
        // Get current password
        $stmt = $this->db->prepare('SELECT password FROM customer_users WHERE id = ?');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
 
        if ($result->num_rows === 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Khách hàng không tồn tại'];
        }
 
        $user = $result->fetch_assoc();
        $stmt->close();
 
        // Verify old password
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu cũ không đúng'];
        }
 
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
 
        // Update password
        $stmt = $this->db->prepare('UPDATE customer_users SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hashedPassword, $customerId);
 
        if ($stmt->execute()) {
            $stmt->close();
            $this->logActivity($customerId, 'change_password', 'Đổi mật khẩu');
            return ['success' => true, 'message' => 'Đổi mật khẩu thành công'];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => 'Lỗi khi đổi mật khẩu'];
        }
    }
 
    /**
     * Update customer profile
     */
    public function updateProfile($customerId, $data) {
        $stmt = $this->db->prepare(
            'UPDATE customer_users SET fullname = ?, phone = ?, nationality = ?, address = ? WHERE id = ?'
        );
 
        $stmt->bind_param(
            'ssssi',
            $data['fullname'],
            $data['phone'],
            $data['nationality'],
            $data['address'],
            $customerId
        );
 
        if ($stmt->execute()) {
            $stmt->close();
            $this->logActivity($customerId, 'update_profile', 'Cập nhật hồ sơ');
            return ['success' => true, 'message' => 'Cập nhật hồ sơ thành công'];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => 'Lỗi khi cập nhật'];
        }
    }
}
 
// API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_GET['action'] ?? '';
    $customerAuth = new CustomerAuth();
    $response = [];
 
    switch ($action) {
        case 'register':
            $data = json_decode(file_get_contents('php://input'), true);
            $response = $customerAuth->register($data);
            break;
 
        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            $response = $customerAuth->login($data['email'] ?? '', $data['password'] ?? '');
            break;
 
        case 'logout':
            $response = $customerAuth->logout();
            break;
 
        default:
            $response = ['success' => false, 'message' => 'Action not found'];
    }
 
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
 
// Check session endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'check') {
    header('Content-Type: application/json; charset=utf-8');
    $customerAuth = new CustomerAuth();
    echo json_encode([
        'isLoggedIn' => $customerAuth->isLoggedIn(),
        'customer' => $customerAuth->getCurrentCustomer()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}