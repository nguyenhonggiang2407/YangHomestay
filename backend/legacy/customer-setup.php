<?php
/**
 * ═════════════════════════════════════════════════════════════════════════
 * CUSTOMER PORTAL - DATABASE SETUP
 * Tạo bảng cho khách hàng và đặt phòng
 * ═════════════════════════════════════════════════════════════════════════
 */
 
require_once __DIR__ . '/config.php';
 
$db = Database::getInstance()->getConnection();
 
echo "═══════════════════════════════════════════════════════\n";
echo "🏨 KHỞI TẠO DATABASE CHO KHÁCH HÀNG\n";
echo "═══════════════════════════════════════════════════════\n\n";
 
// Create customer users table (for customer portal login)
$customerUsersTable = "CREATE TABLE IF NOT EXISTS customer_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(100) NOT NULL,
    idcard VARCHAR(20) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    nationality VARCHAR(50) DEFAULT 'Việt Nam',
    address TEXT,
    avatar_url VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_idcard (idcard),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
 
// Create room gallery table
$roomGalleryTable = "CREATE TABLE IF NOT EXISTS room_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_id (room_id),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
 
// Create customer bookings table
$customerBookingsTable = "CREATE TABLE IF NOT EXISTS customer_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(20) UNIQUE NOT NULL,
    customer_user_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    number_of_nights INT NOT NULL,
    price_per_night INT NOT NULL,
    total_price INT NOT NULL,
    status ENUM('pending', 'confirmed', 'checkin', 'checkout', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    special_requests TEXT,
    payment_status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_user_id),
    INDEX idx_booking_ref (booking_reference),
    INDEX idx_status (status),
    INDEX idx_check_in (check_in),
    FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
 
// Create reviews table
$reviewsTable = "CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_user_id INT NOT NULL,
    room_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_id (customer_user_id),
    INDEX idx_room_id (room_id),
    INDEX idx_status (status),
    FOREIGN KEY (customer_user_id) REFERENCES customer_users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
 
$tables = [
    $customerUsersTable,
    $roomGalleryTable,
    $customerBookingsTable,
    $reviewsTable
];
 
$errorCount = 0;
foreach ($tables as $sql) {
    if ($db->query($sql)) {
        if (strpos($sql, 'customer_users') !== false) {
            echo "✓ Tạo bảng: customer_users\n";
        } elseif (strpos($sql, 'room_gallery') !== false) {
            echo "✓ Tạo bảng: room_gallery\n";
        } elseif (strpos($sql, 'customer_bookings') !== false) {
            echo "✓ Tạo bảng: customer_bookings\n";
        } elseif (strpos($sql, 'reviews') !== false) {
            echo "✓ Tạo bảng: reviews\n";
        }
    } else {
        echo "✗ Lỗi: " . $db->error . "\n";
        $errorCount++;
    }
}
 
echo "\n";
 
// Insert sample customer user
$email = 'customer@example.com';
$stmt = $db->prepare('SELECT id FROM customer_users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
 
if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    
    $fullname = 'Nguyễn Văn Khách';
    $idcard = '001234567890';
    $phone = '0901234567';
    $password = password_hash('customer123', PASSWORD_BCRYPT);
    $dob = '1990-01-15';
    $nationality = 'Việt Nam';
    $address = '123 Đường Nguyễn Huệ, Q.1, TP.HCM';
    $status = 'active';
 
    $stmt = $db->prepare(
        'INSERT INTO customer_users (fullname, idcard, phone, email, password, date_of_birth, nationality, address, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssssss', $fullname, $idcard, $phone, $email, $password, $dob, $nationality, $address, $status);
 
    if ($stmt->execute()) {
        echo "✓ Tạo tài khoản khách hàng mẫu\n";
        echo "  Email: customer@example.com\n";
        echo "  Password: customer123\n";
    } else {
        echo "✗ Lỗi tạo tài khoản khách hàng\n";
        $errorCount++;
    }
    $stmt->close();
} else {
    echo "ℹ️  Tài khoản khách hàng mẫu đã tồn tại\n";
}
 
// Add sample room images
$roomSql = "INSERT IGNORE INTO room_gallery (room_id, image_url, caption, display_order) 
    SELECT id, CONCAT('https://via.placeholder.com/600x400?text=', room_type), room_type, 0 FROM rooms LIMIT 5";
if ($db->query($roomSql)) {
    echo "✓ Thêm ảnh phòng mẫu\n";
}
 
echo "\n═══════════════════════════════════════════════════════\n";
if ($errorCount === 0) {
    echo "✅ KHỞI TẠO THÀNH CÔNG\n";
} else {
    echo "⚠️  KHỞI TẠO VỚI $errorCount LỖI\n";
}
echo "═══════════════════════════════════════════════════════\n";
 
echo "\n📝 THÔNG TIN ĐĂNG NHẬP KHÁCH HÀNG MẪU:\n";
echo "Email: customer@example.com\n";
echo "Password: customer123\n";