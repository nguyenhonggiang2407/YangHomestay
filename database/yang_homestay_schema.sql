-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — DATABASE SCHEMA (PHASE 2)
--
-- • Database mới hoàn toàn riêng biệt: yang_homestay
-- • KHÔNG đụng tới database cũ (hotel_management) — website Phase 1 vẫn chạy bình thường.
-- • Kiến trúc: PROPERTY → UNIT → AVAILABILITY → BOOKING
-- • Charset: utf8mb4 / utf8mb4_unicode_ci (tương thích MySQL & MariaDB, XAMPP, PHP 8+)
-- • File này CHỈ chứa schema (tables, foreign keys, indexes, constraints).
--   Dữ liệu demo nằm ở: yang_homestay_seed.sql
--
-- Cách import:
--   phpMyAdmin: Import file này trước, rồi import yang_homestay_seed.sql
--   CLI:        mysql -u root -p < yang_homestay_schema.sql
--               mysql -u root -p < yang_homestay_seed.sql
--
-- ═════════════════════════════════════════════════════════════════════════
-- QUY ƯỚC NGÀY THÁNG (toàn hệ thống):
--   • check_in  = inclusive (đêm đầu tiên được tính)
--   • check_out = exclusive (không tính đêm cuối)
--   • Ví dụ: 20/08 → 23/08 = 3 đêm (20, 21, 22); khách mới có thể nhận phòng từ 23/08
--
-- QUY ƯỚC TIỀN TỆ:
--   • Giá lưu bằng VND nguyên (BIGINT UNSIGNED), KHÔNG dùng kiểu "850" để ám chỉ 850.000.
-- ═════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS yang_homestay
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE yang_homestay;

-- ═════════════════════════════════════════════════════════════════════════
-- 1. ADMINS — tài khoản quản trị nền tảng
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS admins (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)  NOT NULL,
    email          VARCHAR(100) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,          -- luôn hash bằng password_hash() (bcrypt)
    full_name      VARCHAR(100) NOT NULL,
    role           ENUM('super_admin','manager','staff') NOT NULL DEFAULT 'staff',
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at  TIMESTAMP NULL DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username),
    UNIQUE KEY uq_admins_email (email),
    KEY idx_admins_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 2. CUSTOMERS — tài khoản khách hàng (portal)
--    Nguyên tắc data minimization: chỉ lưu dữ liệu account cần thiết.
--    CCCD / Passport / DOB / Nationality / Address → KHÔNG lưu ở account
--    (sẽ thiết kế bảng check-in riêng sau nếu cần).
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS customers (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name         VARCHAR(100) NOT NULL,
    email             VARCHAR(100) NOT NULL,
    phone             VARCHAR(20)  NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,       -- luôn hash bằng password_hash()
    avatar_url        VARCHAR(255) NULL DEFAULT NULL,
    status            ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    last_login_at     TIMESTAMP NULL DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_phone (phone),
    KEY idx_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 3. PROPERTIES — cơ sở / vị trí lưu trú (tòa nhà, khu nhà)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS properties (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code              VARCHAR(20)  NOT NULL,       -- mã nội bộ, vd: YH-PC
    name              VARCHAR(150) NOT NULL,       -- vd: YangHomestay Phố Cổ
    slug              VARCHAR(180) NOT NULL,
    district          VARCHAR(100) NULL DEFAULT NULL,  -- Quận (vd: Hoàn Kiếm)
    ward              VARCHAR(100) NULL DEFAULT NULL,  -- Phường (vd: Hàng Bạc)
    address           VARCHAR(255) NULL DEFAULT NULL,
    latitude          DECIMAL(10,7) NULL DEFAULT NULL,
    longitude         DECIMAL(10,7) NULL DEFAULT NULL,
    short_description VARCHAR(255) NULL DEFAULT NULL,
    description       TEXT NULL,
    story             TEXT NULL,                   -- câu chuyện thương hiệu (trang about)
    checkin_time      TIME NOT NULL DEFAULT '14:00:00',
    checkout_time     TIME NOT NULL DEFAULT '12:00:00',
    status            ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    featured          TINYINT(1) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_properties_code (code),
    UNIQUE KEY uq_properties_slug (slug),
    KEY idx_properties_district (district),
    KEY idx_properties_status (status),
    KEY idx_properties_featured (featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 4. PROPERTY_IMAGES — ảnh của property (ngoại thất, sảnh, khu vực lân cận...)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS property_images (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id    INT UNSIGNED NOT NULL,
    image_url      VARCHAR(255) NOT NULL,
    caption        VARCHAR(255) NULL DEFAULT NULL,
    image_category VARCHAR(30)  NOT NULL DEFAULT 'other',  -- exterior|lobby|neighborhood|view|common_area|other
    display_order  INT NOT NULL DEFAULT 0,
    is_cover       TINYINT(1) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_property_images_property_url (property_id, image_url),
    KEY idx_property_images_property (property_id, display_order),
    CONSTRAINT fk_property_images_property FOREIGN KEY (property_id)
        REFERENCES properties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 5. UNITS — căn thực tế mà khách đặt (booking luôn trỏ tới unit_id)
--    KHÔNG dùng trạng thái 'occupied' — unit có khách hay không phải
--    xác định qua booking theo từng ngày.
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS units (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id       INT UNSIGNED NOT NULL,
    code              VARCHAR(20)  NOT NULL,       -- mã nội bộ, vd: YH-PC-01
    name              VARCHAR(150) NOT NULL,
    slug              VARCHAR(180) NOT NULL,
    type              VARCHAR(20)  NOT NULL DEFAULT 'studio',  -- studio|apartment|loft|couple|family|villa|house (VARCHAR để dễ mở rộng, validate ở backend)
    base_price        BIGINT UNSIGNED NOT NULL,    -- VND nguyên (850000, không phải 850)
    cleaning_fee      BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- VND nguyên
    max_guests        SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    bedrooms          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    beds              SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    bathrooms         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    area_m2           SMALLINT UNSIGNED NULL DEFAULT NULL,
    short_description VARCHAR(255) NULL DEFAULT NULL,
    description       TEXT NULL,
    house_rules       TEXT NULL,
    status            ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    featured          TINYINT(1) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_units_code (code),
    UNIQUE KEY uq_units_slug (slug),
    KEY idx_units_property (property_id),
    KEY idx_units_status (status),
    KEY idx_units_base_price (base_price),
    KEY idx_units_max_guests (max_guests),
    KEY idx_units_featured (featured),
    CONSTRAINT chk_units_price CHECK (base_price >= 0),
    CONSTRAINT chk_units_guests CHECK (max_guests >= 1),
    CONSTRAINT fk_units_property FOREIGN KEY (property_id)
        REFERENCES properties (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 6. UNIT_IMAGES — ảnh của từng unit
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS unit_images (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id        INT UNSIGNED NOT NULL,
    image_url      VARCHAR(255) NOT NULL,
    caption        VARCHAR(255) NULL DEFAULT NULL,
    image_category VARCHAR(30)  NOT NULL DEFAULT 'other',  -- bedroom|bathroom|kitchen|balcony|view|workspace|living_room|building|other
    display_order  INT NOT NULL DEFAULT 0,
    is_cover       TINYINT(1) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unit_images_unit_url (unit_id, image_url),
    KEY idx_unit_images_unit_order (unit_id, display_order),
    CONSTRAINT fk_unit_images_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 7. AMENITIES — danh mục tiện nghi (dùng chung toàn hệ thống)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS amenities (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(40)  NOT NULL,              -- vd: wifi, air_conditioner
    name_vi    VARCHAR(100) NOT NULL,
    name_en    VARCHAR(100) NOT NULL,
    icon       VARCHAR(50)  NULL DEFAULT NULL,
    category   VARCHAR(30)  NOT NULL DEFAULT 'essential',  -- essential|kitchen|bathroom|entertainment|workspace|outdoor|parking|accessibility|view
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_amenities_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 8. UNIT_AMENITIES — junction: unit ↔ amenity
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS unit_amenities (
    unit_id    INT UNSIGNED NOT NULL,
    amenity_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (unit_id, amenity_id),
    KEY idx_unit_amenities_amenity (amenity_id),
    CONSTRAINT fk_unit_amenities_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE CASCADE,
    CONSTRAINT fk_unit_amenities_amenity FOREIGN KEY (amenity_id)
        REFERENCES amenities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 9. EXPERIENCE_TAGS — nhãn trải nghiệm (đầu vào cho YangMatch)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS experience_tags (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code     VARCHAR(40)  NOT NULL,                -- vd: couple, workcation, lake-view
    name_vi  VARCHAR(100) NOT NULL,
    name_en  VARCHAR(100) NOT NULL,
    category VARCHAR(30)  NOT NULL DEFAULT 'style',  -- guest_type|style|activity|location|work
    active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_experience_tags_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 10. UNIT_EXPERIENCE_TAGS — junction: unit ↔ tag, có weight cho YangMatch
--     weight: mức độ khớp (vd: couple=10, romantic=9, quiet=8)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS unit_experience_tags (
    unit_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    weight  SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    PRIMARY KEY (unit_id, tag_id),
    KEY idx_unit_experience_tags_tag (tag_id),
    CONSTRAINT chk_unit_experience_tags_weight CHECK (weight BETWEEN 1 AND 10),
    CONSTRAINT fk_unit_experience_tags_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE CASCADE,
    CONSTRAINT fk_unit_experience_tags_tag FOREIGN KEY (tag_id)
        REFERENCES experience_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 11. AVAILABILITY_BLOCKS — chặn ngày bán của unit (maintenance, owner_use...)
--     QUY ƯỚC: start_date = inclusive, end_date = exclusive
--     Ví dụ 20/08 → 23/08 chặn các đêm 20, 21, 22; bán lại từ 23/08
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS availability_blocks (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id             INT UNSIGNED NOT NULL,
    start_date          DATE NOT NULL,             -- inclusive
    end_date            DATE NOT NULL,             -- exclusive
    reason              VARCHAR(30) NOT NULL DEFAULT 'manual_block',  -- maintenance|owner_use|cleaning|manual_block|other
    note                VARCHAR(500) NULL DEFAULT NULL,
    created_by_admin_id INT UNSIGNED NULL DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_availability_blocks_unit_dates (unit_id, start_date, end_date),
    CONSTRAINT chk_availability_blocks_dates CHECK (end_date > start_date),
    CONSTRAINT fk_availability_blocks_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE CASCADE,
    CONSTRAINT fk_availability_blocks_admin FOREIGN KEY (created_by_admin_id)
        REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 12. RATE_RULES — luật giá linh hoạt (weekday / weekend / holiday / seasonal)
--     • day_of_week: 1=Chủ nhật ... 7=Thứ 7 (quy ước DAYOFWEEK() của MySQL), NULL = mọi ngày
--     • start_date / end_date: NULL = không giới hạn
--     • Nếu overlap: priority cao hơn thắng.
--       Cùng priority: rule cụ thể hơn thắng (có day_of_week > NULL;
--       khoảng ngày hẹp hơn > rộng hơn); cuối cùng: rule updated_at mới hơn.
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS rate_rules (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(150) NOT NULL,              -- vd: Cuối tuần Thứ 7
    start_date DATE NULL DEFAULT NULL,             -- NULL = áp dụng từ đầu
    end_date   DATE NULL DEFAULT NULL,             -- NULL = áp dụng mãi mãi
    day_of_week TINYINT UNSIGNED NULL DEFAULT NULL, -- 1=CN ... 7=T7, NULL = mọi ngày trong tuần
    price      BIGINT UNSIGNED NOT NULL,           -- giá đêm khi rule áp dụng
    priority   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_rules_unit (unit_id),
    KEY idx_rate_rules_dates (start_date, end_date),
    CONSTRAINT chk_rate_rules_dow CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_rate_rules_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date > start_date),
    CONSTRAINT fk_rate_rules_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 13. PROMOTIONS — mã khuyến mãi
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS promotions (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                    VARCHAR(40)  NOT NULL,
    name                    VARCHAR(150) NOT NULL,
    description             VARCHAR(500) NULL DEFAULT NULL,
    discount_type           ENUM('percentage','fixed_amount') NOT NULL,
    discount_value          BIGINT UNSIGNED NOT NULL,  -- % hoặc số tiền VND
    minimum_booking_value   BIGINT UNSIGNED NULL DEFAULT NULL,
    maximum_discount        BIGINT UNSIGNED NULL DEFAULT NULL,  -- trần chiết khấu (áp dụng cho %)
    valid_from              DATE NOT NULL,
    valid_until             DATE NOT NULL,
    usage_limit             INT UNSIGNED NULL DEFAULT NULL,         -- NULL = không giới hạn
    usage_limit_per_customer INT UNSIGNED NULL DEFAULT NULL,
    active                  TINYINT(1) NOT NULL DEFAULT 1,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promotions_code (code),
    KEY idx_promotions_active (active),
    KEY idx_promotions_valid (valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 14. BOOKINGS — bảng quan trọng nhất
--     • check_in inclusive / check_out exclusive (20/08 → 23/08 = 3 đêm)
--     • Overlap: new_check_in < existing_check_out AND new_check_out > existing_check_in
--       chỉ xét booking status: pending, confirmed, checkin
--       (cancelled / checkout không chặn availability)
--     • subtotal  = tổng booking_nights (giá snapshot từng đêm)
--     • total     = subtotal + cleaning_fee − discount_amount
--     • booking_reference do backend Phase 3 generate (vd: YH202608160001)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS bookings (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_reference     VARCHAR(20)  NOT NULL,
    customer_id           INT UNSIGNED NOT NULL,
    unit_id               INT UNSIGNED NOT NULL,

    guest_name            VARCHAR(100) NOT NULL,   -- snapshot tại thời điểm đặt
    guest_email           VARCHAR(100) NULL DEFAULT NULL,
    guest_phone           VARCHAR(20)  NULL DEFAULT NULL,

    check_in              DATE NOT NULL,           -- inclusive
    check_out             DATE NOT NULL,           -- exclusive
    number_of_nights      SMALLINT UNSIGNED NOT NULL,

    adults                SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    children              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_guests          SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    subtotal              BIGINT UNSIGNED NOT NULL,    -- = SUM(booking_nights.nightly_rate)
    cleaning_fee          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discount_amount       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_amount          BIGINT UNSIGNED NOT NULL,    -- = subtotal + cleaning_fee − discount_amount
    currency              CHAR(3) NOT NULL DEFAULT 'VND',

    promotion_id          INT UNSIGNED NULL DEFAULT NULL,
    promotion_code_snapshot VARCHAR(40) NULL DEFAULT NULL,  -- lưu mã promo tại thời điểm đặt

    status                ENUM('pending','confirmed','checkin','checkout','cancelled') NOT NULL DEFAULT 'pending',
    payment_status        ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid',

    special_requests      TEXT NULL,
    cancellation_reason   VARCHAR(500) NULL DEFAULT NULL,

    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    confirmed_at          TIMESTAMP NULL DEFAULT NULL,
    checked_in_at         TIMESTAMP NULL DEFAULT NULL,
    checked_out_at        TIMESTAMP NULL DEFAULT NULL,
    cancelled_at          TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_bookings_reference (booking_reference),
    KEY idx_bookings_customer (customer_id),
    KEY idx_bookings_unit (unit_id),
    KEY idx_bookings_status (status),
    KEY idx_bookings_check_in (check_in),
    KEY idx_bookings_check_out (check_out),
    KEY idx_bookings_created_at (created_at),
    -- Composite index phục vụ availability check:
    --   new_check_in < existing_check_out AND new_check_out > existing_check_in
    KEY idx_bookings_availability (unit_id, status, check_in, check_out),
    CONSTRAINT chk_bookings_dates CHECK (check_out > check_in),
    CONSTRAINT chk_bookings_nights CHECK (number_of_nights >= 1),
    CONSTRAINT chk_bookings_guests CHECK (total_guests >= 1),
    CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_promotion FOREIGN KEY (promotion_id)
        REFERENCES promotions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 15. BOOKING_NIGHTS — price snapshot theo từng đêm của booking
--     Admin đổi giá tương lai KHÔNG ảnh hưởng booking cũ.
--     Ví dụ: 21/08=850000, 22/08=1050000, 23/08=1050000
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS booking_nights (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id     INT UNSIGNED NOT NULL,
    stay_date      DATE NOT NULL,
    nightly_rate   BIGINT UNSIGNED NOT NULL,       -- giá đêm đã khóa tại thời điểm đặt
    rate_rule_id   INT UNSIGNED NULL DEFAULT NULL, -- rule nào đã áp dụng cho đêm này (nếu có)
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_nights (booking_id, stay_date),
    KEY idx_booking_nights_stay_date (stay_date),
    CONSTRAINT fk_booking_nights_booking FOREIGN KEY (booking_id)
        REFERENCES bookings (id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_nights_rate_rule FOREIGN KEY (rate_rule_id)
        REFERENCES rate_rules (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 16. BOOKING_PRICE_ITEMS — chi tiết dòng tiền để hiển thị hóa đơn
--     Ví dụ:
--       3 đêm                2.700.000 ₫  (accommodation)
--       Cleaning fee           100.000 ₫  (cleaning_fee)
--       Discount              -150.000 ₫  (discount)
--       ────────────────────────────────
--       Total                2.650.000 ₫
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS booking_price_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id    INT UNSIGNED NOT NULL,
    item_type     VARCHAR(30) NOT NULL DEFAULT 'other',  -- accommodation|cleaning_fee|discount|extra_guest|other
    label         VARCHAR(150) NOT NULL,
    amount        BIGINT NOT NULL,                 -- số âm cho discount
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_price_items_booking (booking_id, display_order),
    CONSTRAINT fk_booking_price_items_booking FOREIGN KEY (booking_id)
        REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 17. PROMOTION_USAGES — lịch sử dùng mã khuyến mãi
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS promotion_usages (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    promotion_id    INT UNSIGNED NOT NULL,
    customer_id     INT UNSIGNED NOT NULL,
    booking_id      INT UNSIGNED NOT NULL,
    discount_amount BIGINT UNSIGNED NOT NULL,
    used_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promotion_usages_booking (booking_id),
    KEY idx_promotion_usages_promo_customer (promotion_id, customer_id),
    CONSTRAINT fk_promotion_usages_promotion FOREIGN KEY (promotion_id)
        REFERENCES promotions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_promotion_usages_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_promotion_usages_booking FOREIGN KEY (booking_id)
        REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 18. FAVORITES — yêu thích của khách hàng
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS favorites (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    unit_id     INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_favorites_customer_unit (customer_id, unit_id),
    KEY idx_favorites_unit (unit_id),
    CONSTRAINT fk_favorites_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 19. REVIEWS — đánh giá, luôn gắn với booking THẬT (1 booking → tối đa 1 review)
--     Backend Phase sau chỉ cho review khi:
--       bookings.status = 'checkout' AND bookings.customer_id = user đang đăng nhập
--     "Verified stay" suy ra được từ booking, không cần cột riêng.
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS reviews (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id            INT UNSIGNED NOT NULL,
    customer_id           INT UNSIGNED NOT NULL,
    unit_id               INT UNSIGNED NOT NULL,

    overall_rating        TINYINT UNSIGNED NOT NULL,
    cleanliness_rating    TINYINT UNSIGNED NOT NULL,
    location_rating       TINYINT UNSIGNED NOT NULL,
    amenities_rating      TINYINT UNSIGNED NOT NULL,
    accuracy_rating       TINYINT UNSIGNED NOT NULL,
    communication_rating  TINYINT UNSIGNED NOT NULL,
    value_rating          TINYINT UNSIGNED NOT NULL,

    title                 VARCHAR(255) NULL DEFAULT NULL,
    comment               TEXT NULL,

    status                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at           TIMESTAMP NULL DEFAULT NULL,
    approved_by_admin_id  INT UNSIGNED NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_booking (booking_id),
    KEY idx_reviews_unit (unit_id),
    KEY idx_reviews_customer (customer_id),
    KEY idx_reviews_status (status),
    CONSTRAINT chk_reviews_ratings CHECK (
        overall_rating BETWEEN 1 AND 5 AND
        cleanliness_rating BETWEEN 1 AND 5 AND
        location_rating BETWEEN 1 AND 5 AND
        amenities_rating BETWEEN 1 AND 5 AND
        accuracy_rating BETWEEN 1 AND 5 AND
        communication_rating BETWEEN 1 AND 5 AND
        value_rating BETWEEN 1 AND 5
    ),
    CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id)
        REFERENCES bookings (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reviews_unit FOREIGN KEY (unit_id)
        REFERENCES units (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reviews_admin FOREIGN KEY (approved_by_admin_id)
        REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 20. NEARBY_PLACES — địa điểm lân cận (Yang Hanoi Guide / Map / Itinerary)
--     distance_meters / walking_minutes: dữ liệu THẬT khi có, demo phải đánh dấu rõ.
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS nearby_places (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id       INT UNSIGNED NOT NULL,
    name              VARCHAR(150) NOT NULL,
    category          VARCHAR(30)  NOT NULL DEFAULT 'attraction',  -- food|coffee|attraction|nightlife|shopping|photography|transport
    short_description VARCHAR(255) NULL DEFAULT NULL,
    latitude          DECIMAL(10,7) NULL DEFAULT NULL,
    longitude         DECIMAL(10,7) NULL DEFAULT NULL,
    distance_meters   INT UNSIGNED NULL DEFAULT NULL,
    walking_minutes   SMALLINT UNSIGNED NULL DEFAULT NULL,
    image_url         VARCHAR(255) NULL DEFAULT NULL,
    featured          TINYINT(1) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nearby_places_property (property_id),
    KEY idx_nearby_places_category (category),
    CONSTRAINT fk_nearby_places_property FOREIGN KEY (property_id)
        REFERENCES properties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 21. NOTIFICATIONS — thông báo cho khách hàng hoặc admin
--     type: booking_created|booking_confirmed|booking_cancelled|
--           checkin_reminder|review_invitation|new_booking_admin
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NULL DEFAULT NULL,    -- NULL nếu là notification cho admin
    admin_id    INT UNSIGNED NULL DEFAULT NULL,    -- NULL nếu là notification cho customer
    type        VARCHAR(40)  NOT NULL,
    title       VARCHAR(150) NOT NULL,
    message     VARCHAR(500) NULL DEFAULT NULL,
    entity_type VARCHAR(30)  NULL DEFAULT NULL,    -- vd: booking, unit, review
    entity_id   INT UNSIGNED NULL DEFAULT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_customer (customer_id, is_read),
    KEY idx_notifications_admin (admin_id, is_read),
    KEY idx_notifications_created (created_at),
    CONSTRAINT fk_notifications_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_admin FOREIGN KEY (admin_id)
        REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 22. CONTACT_MESSAGES — tin nhắn liên hệ từ website
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL,
    phone      VARCHAR(20)  NULL DEFAULT NULL,
    subject    VARCHAR(150) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contact_messages_status (status),
    KEY idx_contact_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 23. LOGIN_LOGS — lịch sử đăng nhập (KHÔNG log password)
--     Không có foreign key: log phải tồn tại ngay cả khi account bị xóa.
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS login_logs (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type       ENUM('customer','admin') NOT NULL,
    actor_id         INT UNSIGNED NULL DEFAULT NULL,
    email_or_username VARCHAR(120) NOT NULL,
    success          TINYINT(1) NULL DEFAULT NULL,
    ip_address       VARCHAR(45) NULL DEFAULT NULL,
    user_agent       TEXT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_logs_actor (actor_type, actor_id),
    KEY idx_login_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- 24. AUDIT_LOGS — nhật ký thao tác admin (CHANGE_BOOKING_STATUS, UPDATE_UNIT_PRICE...)
-- ═════════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS audit_logs (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id       INT UNSIGNED NULL DEFAULT NULL,
    action         VARCHAR(50)  NOT NULL,          -- CHANGE_BOOKING_STATUS|UPDATE_UNIT_PRICE|DELETE_UNIT|BLOCK_DATES|UPDATE_PROPERTY|APPROVE_REVIEW...
    entity_type    VARCHAR(30)  NOT NULL,
    entity_id      INT UNSIGNED NULL DEFAULT NULL,
    old_value_json JSON NULL DEFAULT NULL,
    new_value_json JSON NULL DEFAULT NULL,
    ip_address     VARCHAR(45) NULL DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    KEY idx_audit_logs_admin (admin_id),
    KEY idx_audit_logs_created (created_at),
    CONSTRAINT fk_audit_logs_admin FOREIGN KEY (admin_id)
        REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════
-- GHI CHÚ THIẾT KẾ
-- ═════════════════════════════════════════════════════════════════════════
-- 1. Availability tổng hợp (không có bảng availability riêng):
--      unit khả dụng cho ngày D ⟺
--        (a) KHÔNG có booking active (pending|confirmed|checkin) nào chứa D, VÀ
--        (b) KHÔNG có availability_block nào chứa D.
--    Query chuẩn (Phase 3 backend dùng transaction trước INSERT):
--      SELECT COUNT(*) FROM bookings
--      WHERE unit_id = ? AND status IN ('pending','confirmed','checkin')
--        AND new_check_in < check_out AND new_check_out > check_in;
-- 2. Giá luôn snapshot theo từng đêm (booking_nights) → đổi giá tương lai
--    không làm booking cũ đổi giá.
-- 3. Không dùng ON DELETE CASCADE mù quáng: lịch sử booking/review được bảo vệ
--    bằng RESTRICT + soft-disable (status = inactive) thay vì xóa cứng.
--    Chi tiết xem database/README.md.
-- ═════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 1;
