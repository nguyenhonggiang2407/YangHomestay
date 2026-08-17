-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — DATABASE SCHEMA (MỚI, TÁCH BIỆT)
--
-- • File này KHÔNG đụng tới database cũ (hotel_management / hotel_system).
-- • Schema mới dùng database riêng: yang_homestay
-- • Cách import:
--     phpMyAdmin: Import file này
--     hoặc CLI:   mysql -u root -p < yang_homestay.sql
--
-- Kiến trúc: Property → Units → Availability → Booking
-- ═════════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS yang_homestay
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE yang_homestay;

-- ═════════════════════════════════════════════════════════════════════════
-- 1. CUSTOMERS — khách hàng (đăng nhập portal)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    date_of_birth DATE NULL,
    nationality VARCHAR(50) DEFAULT 'Việt Nam',
    address TEXT NULL,
    avatar_url VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 2. ADMINS — tài khoản quản trị (admin / staff)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 3. PROPERTIES — căn hộ / cơ sở lưu trú (tòa nhà, khu nhà)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS properties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) UNIQUE NOT NULL,
    property_type ENUM('apartment', 'villa', 'homestay', 'hotel') DEFAULT 'homestay',
    description TEXT NULL,
    address VARCHAR(255) NULL,
    district VARCHAR(100) NULL,          -- Quận/Huyện tại Hà Nội (vd: Hoàn Kiếm)
    lat DECIMAL(10, 7) NULL,             -- vĩ độ (Google/OSM)
    lng DECIMAL(10, 7) NULL,             -- kinh độ
    amenities TEXT NULL,                 -- JSON array, vd: ["wifi","parking","kitchen"]
    cover_image VARCHAR(255) NULL,
    gallery TEXT NULL,                   -- JSON array các ảnh
    status ENUM('active', 'inactive') DEFAULT 'active',
    rating_avg DECIMAL(2, 1) DEFAULT 0.0,
    review_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_district (district),
    INDEX idx_status (status),
    INDEX idx_type (property_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 4. UNITS — từng căn phòng/căn hộ thuộc property
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    unit_type VARCHAR(50) NULL,          -- vd: Studio, 1BR, 2BR, Penthouse
    capacity INT NOT NULL DEFAULT 2,     -- số khách tối đa
    bedrooms INT DEFAULT 1,
    bathrooms INT DEFAULT 1,
    price_per_night INT NOT NULL,        -- VND
    size_m2 INT NULL,
    amenities TEXT NULL,                 -- JSON array
    description TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property (property_id),
    INDEX idx_type (unit_type),
    INDEX idx_status (status),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 5. AVAILABILITY — trạng thái từng ngày của từng unit
--    (cốt lõi: chống đặt trùng — mỗi unit mỗi ngày chỉ có 1 trạng thái)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('available', 'booked', 'blocked') DEFAULT 'available',
    price_override INT NULL,             -- giá riêng cho ngày đó (nếu có)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unit_date (unit_id, date),
    INDEX idx_unit (unit_id),
    INDEX idx_date (date),
    INDEX idx_status (status),
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 6. BOOKINGS — đặt phòng
--    check_in là đêm đầu, check_out là ngày trả phòng (không tính đêm cuối)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_reference VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    unit_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    number_of_nights INT NOT NULL,
    price_per_night INT NOT NULL,
    total_price INT NOT NULL,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid',
    guest_name VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(20) NULL,
    guest_email VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_unit (unit_id),
    INDEX idx_status (status),
    INDEX idx_check_in (check_in),
    INDEX idx_check_out (check_out),
    INDEX idx_reference (booking_reference),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 7. FAVORITES — yêu thích của khách
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    unit_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_unit (customer_id, unit_id),
    INDEX idx_customer (customer_id),
    INDEX idx_unit (unit_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 8. REVIEWS — đánh giá (cần admin duyệt trước khi hiển thị)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NULL,                 -- review chỉ từ đơn đã lưu trú (nếu có)
    customer_id INT NOT NULL,
    unit_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_unit (unit_id),
    INDEX idx_status (status),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 9. LOGS
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS login_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    success TINYINT(1) NULL,
    message VARCHAR(255) NULL,
    ip_address VARCHAR(50) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    actor_type ENUM('admin', 'customer') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- SEED DỮ LIỆU MẪU (có thể xóa nếu không cần)
-- ═════════════════════════════════════════════════════════════════════════

-- Admin mặc định (đổi mật khẩu sau khi đăng nhập!)
-- username: admin / password: admin123
INSERT INTO admins (username, password_hash, fullname, email, role, status)
VALUES ('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFx4VWo3Z2zJ7vzDkYzGfqE5y1y9z5aG', 'Quản trị viên', 'admin@yanghomestay.com', 'admin', 'active')
ON DUPLICATE KEY UPDATE username = username;

-- Property mẫu: căn hộ tại Hà Nội
INSERT INTO properties (name, slug, property_type, description, address, district, lat, lng, amenities, cover_image, status)
VALUES (
    'YangHomestay Old Quarter',
    'yanghomestay-old-quarter',
    'homestay',
    'Căn hộ homestay ấm cúng ngay phố cổ Hà Nội, phù hợp khách du lịch muốn trải nghiệm không gian Hà Thành.',
    '12 Hàng Bạc, Hoàn Kiếm, Hà Nội',
    'Hoàn Kiếm',
    21.0333330,
    105.8500000,
    '["wifi","air_conditioner","kitchen","washing_machine"]',
    'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=1200',
    'active'
);

-- Unit mẫu thuộc property trên
INSERT INTO units (property_id, name, unit_type, capacity, bedrooms, bathrooms, price_per_night, size_m2, amenities, description, status, sort_order)
SELECT id, 'Studio Ấm Cúng', 'Studio', 2, 1, 1, 550000, 28, '["wifi","air_conditioner"]', 'Studio nhỏ xinh với cửa sổ nhìn ra phố cổ.', 'active', 1
FROM properties WHERE slug = 'yanghomestay-old-quarter';

INSERT INTO units (property_id, name, unit_type, capacity, bedrooms, bathrooms, price_per_night, size_m2, amenities, description, status, sort_order)
SELECT id, 'Căn Hộ 1 Phòng Ngủ', '1BR', 3, 1, 1, 750000, 42, '["wifi","air_conditioner","kitchen"]', 'Phòng ngủ riêng, bếp nhỏ tiện nấu ăn.', 'active', 2
FROM properties WHERE slug = 'yanghomestay-old-quarter';

INSERT INTO units (property_id, name, unit_type, capacity, bedrooms, bathrooms, price_per_night, size_m2, amenities, description, status, sort_order)
SELECT id, 'Gác Xép Gia Đình', '2BR', 5, 2, 2, 1200000, 68, '["wifi","air_conditioner","kitchen","washing_machine"]', 'Không gian rộng cho gia đình 4-5 người.', 'active', 3
FROM properties WHERE slug = 'yanghomestay-old-quarter';

-- ═════════════════════════════════════════════════════════════════════════
-- GHI CHÚ
-- 1. availability có thể sinh tự động theo unit khi admin tạo unit,
--    hoặc sinh lazy khi có booking/search (đề xuất sinh lazy ở Phase 2).
-- 2. Khi tạo booking: cần check trùng (SELECT ... WHERE unit_id = ? AND date BETWEEN check_in AND check_out - INTERVAL 1 DAY AND status != 'available')
--    rồi mới INSERT booking + cập nhật availability thành 'booked'.
-- ═════════════════════════════════════════════════════════════════════════
