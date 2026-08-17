<?php
/**
 * ═════════════════════════════════════════════════════════════════════════
 * DATABASE CONFIGURATION
 * Cấu hình kết nối cơ sở dữ liệu
 * ═════════════════════════════════════════════════════════════════════════
 */

// Database credentials
define('DB_HOST', 'localhost');      // MySQL host (mặc định localhost)
define('DB_USER', 'root');           // MySQL username (mặc định root)
define('DB_PASS', '');               // MySQL password (để trống nếu không có)
define('DB_NAME', 'hotel_management'); // Database name

// Application settings
define('APP_NAME', 'YangHomestay'); // Tên nền tảng (UI sẽ cập nhật ở Phase 3)
define('APP_ENV', 'development');   // 'development' | 'production'
define('APP_URL', 'http://localhost'); // Thay đổi URL của bạn

// Các origin được phép truy cập API (dùng khi frontend chạy khác origin/port)
define('ALLOWED_ORIGINS', [
    APP_URL,
    'http://localhost:5501',
    'http://127.0.0.1',
    'http://127.0.0.1:5501'
]);

// Session settings
ini_set('session.gc_maxlifetime', 3600); // 1 giờ
define('SESSION_TIMEOUT', 3600);

// Cấu hình cookie session an toàn (chạy trước session_start)
session_set_cookie_params([
    'lifetime' => 0,                 // cookie theo phiên (đóng trình duyệt là hết)
    'path'     => '/',
    'domain'   => '',
    'secure'   => false,             // bật true khi dùng HTTPS
    'httponly' => true,              // JS không đọc được cookie session
    'samesite' => 'Lax'              // chống CSRF cơ bản
]);

// Error handling
ini_set('display_errors', APP_ENV === 'development' ? 1 : 0); // production KHÔNG hiện lỗi ra trình duyệt
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if not exists
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Start session
session_start();

/**
 * Database connection
 */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            // Check connection
            if ($this->connection->connect_error) {
                throw new Exception('Kết nối cơ sở dữ liệu thất bại: ' . $this->connection->connect_error);
            }
            
            // Set charset
            $this->connection->set_charset('utf8mb4');
        } catch (Exception $e) {
            die('Lỗi cơ sở dữ liệu: ' . $e->getMessage());
        }
    }

    /**
     * Singleton pattern - Get database instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Execute query
     */
    public function query($sql) {
        return $this->connection->query($sql);
    }

    /**
     * Prepare statement
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    /**
     * Get affected rows
     */
    public function affectedRows() {
        return $this->connection->affected_rows;
    }

    /**
     * Escape string
     */
    public function escape($str) {
        return $this->connection->real_escape_string($str);
    }

    /**
     * Close connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\';');
