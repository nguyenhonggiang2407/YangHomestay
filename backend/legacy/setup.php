<?php
/**
 * ═════════════════════════════════════════════════════════════════════════
 * DATABASE SETUP & INITIALIZATION
 * Tạo bảng và dữ liệu mẫu
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/config.php';

$db = Database::getInstance()->getConnection();

echo "═══════════════════════════════════════════════════════\n";
echo "🔧 KHỞI TẠO CƠ SỞ DỮ LIỆU\n";
echo "═══════════════════════════════════════════════════════\n\n";

// SQL statements
$tables = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        INDEX idx_username (username),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Rooms table
    "CREATE TABLE IF NOT EXISTS rooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_number VARCHAR(20) UNIQUE NOT NULL,
        room_type VARCHAR(50) NOT NULL,
        price INT NOT NULL,
        capacity INT NOT NULL,
        floor INT NOT NULL,
        status ENUM('available', 'occupied', 'cleaning', 'maintenance') DEFAULT 'available',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        INDEX idx_room_number (room_number),
        INDEX idx_status (status),
        INDEX idx_room_type (room_type),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Customers table
    "CREATE TABLE IF NOT EXISTS customers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        fullname VARCHAR(100) NOT NULL,
        idcard VARCHAR(20) UNIQUE NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(100),
        nationality VARCHAR(50) DEFAULT 'Việt Nam',
        date_of_birth DATE,
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        INDEX idx_idcard (idcard),
        INDEX idx_phone (phone),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Bookings table
    "CREATE TABLE IF NOT EXISTS bookings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        customer_id INT NOT NULL,
        room_id INT NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        number_of_nights INT NOT NULL,
        price_per_night INT NOT NULL,
        total_price INT NOT NULL,
        status ENUM('confirmed', 'checkin', 'checkout', 'cancelled') DEFAULT 'confirmed',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        INDEX idx_customer_id (customer_id),
        INDEX idx_room_id (room_id),
        INDEX idx_status (status),
        INDEX idx_check_in (check_in),
        INDEX idx_check_out (check_out),
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Login logs table
    "CREATE TABLE IF NOT EXISTS login_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        success TINYINT(1),
        message VARCHAR(255),
        ip_address VARCHAR(50),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Activity logs table
    "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50),
        entity_id INT,
        description TEXT,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Create tables
$errorCount = 0;
foreach ($tables as $i => $sql) {
    if ($db->query($sql)) {
        $tableName = $i === 0 ? 'users' : ($i === 1 ? 'rooms' : ($i === 2 ? 'customers' : ($i === 3 ? 'bookings' : ($i === 4 ? 'login_logs' : 'activity_logs'))));
        echo "✓ Tạo bảng: $tableName\n";
    } else {
        echo "✗ Lỗi tạo bảng: " . $db->error . "\n";
        $errorCount++;
    }
}

echo "\n";

// Insert default admin user
$username = 'admin';
$password = password_hash('admin123', PASSWORD_BCRYPT);
$fullname = 'Quản trị viên';
$email = 'admin@grandpalace.com';
$role = 'admin';
$status = 'active';

$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    $stmt = $db->prepare('INSERT INTO users (username, password, fullname, email, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $username, $password, $fullname, $email, $role, $status);
    
    if ($stmt->execute()) {
        echo "✓ Tạo tài khoản admin mặc định\n";
        echo "  Username: admin\n";
        echo "  Password: admin123\n";
        echo "  ⚠️  Vui lòng đổi mật khẩu sau khi đăng nhập!\n";
    } else {
        echo "✗ Lỗi tạo tài khoản admin\n";
        $errorCount++;
    }
} else {
    echo "ℹ️  Tài khoản admin đã tồn tại\n";
}
$stmt->close();

// Insert default staff user
$staffUsername = 'staff';
$staffPassword = password_hash('staff123', PASSWORD_BCRYPT);
$staffName = 'Nhân viên';
$staffEmail = 'staff@grandpalace.com';
$staffRole = 'staff';

$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
$stmt->bind_param('s', $staffUsername);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    $stmt = $db->prepare('INSERT INTO users (username, password, fullname, email, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $staffUsername, $staffPassword, $staffName, $staffEmail, $staffRole, $status);
    
    if ($stmt->execute()) {
        echo "✓ Tạo tài khoản nhân viên mặc định\n";
        echo "  Username: staff\n";
        echo "  Password: staff123\n";
    } else {
        echo "✗ Lỗi tạo tài khoản nhân viên\n";
        $errorCount++;
    }
} else {
    echo "ℹ️  Tài khoản nhân viên đã tồn tại\n";
}
$stmt->close();

echo "\n═══════════════════════════════════════════════════════\n";
if ($errorCount === 0) {
    echo "✅ KHỞI TẠO CƠ SỞ DỮ LIỆU THÀNH CÔNG\n";
} else {
    echo "⚠️  KHỞI TẠO HOÀN TẤT VỚI $errorCount LỖI\n";
}
echo "═══════════════════════════════════════════════════════\n";

echo "\n📝 HƯỚNG DẪN KẾT NỐI:\n";
echo "1. Mở phpMyAdmin tại: http://localhost/phpmyadmin\n";
echo "2. Kiểm tra database 'hotel_management' đã được tạo\n";
echo "3. Kiểm tra các bảng: users, rooms, customers, bookings, login_logs, activity_logs\n";
echo "4. Kiểm tra tài khoản mặc định đã được tạo\n\n";

echo "🔐 THÔNG TIN ĐĂNG NHẬP MẶC ĐỊNH:\n";
echo "Admin:\n";
echo "  - Username: admin\n";
echo "  - Password: admin123\n\n";
echo "Staff:\n";
echo "  - Username: staff\n";
echo "  - Password: staff123\n\n";
echo "⚠️  QUAN TRỌNG: Hãy đổi mật khẩu mặc định sau khi đăng nhập!\n";
