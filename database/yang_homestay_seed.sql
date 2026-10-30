-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — SEED DATA (DEVELOPMENT / DEMO ONLY)
--
-- • Dữ liệu demo cho database yang_homestay — KHÔNG dùng cho production.
-- • Production chỉ cần import yang_homestay_schema.sql (không cần seed).
-- • File này TRUNCATE toàn bộ bảng của yang_homestay trước khi insert
--   → dùng để RESET development database về trạng thái demo mẫu.
--   (Chỉ đụng tới yang_homestay — KHÔNG đụng hotel_management)
--
-- MẬT KHẨU DEMO (BẮT BUỘC ĐỔI TRƯỚC KHI LÊN PRODUCTION):
--   admin     / admin123      (super_admin)
--   manager   / manager123    (manager)
--   staff     / staff123      (staff)
--   Tất cả khách demo        / customer123
--   → Password lưu dạng bcrypt, tương thích password_hash() / password_verify()
--
-- QUY ƯỚC DỮ LIỆU:
--   • check_in inclusive / check_out exclusive (20/08 → 23/08 = 3 đêm)
--   • subtotal = SUM(booking_nights.nightly_rate)
--   • total    = subtotal + cleaning_fee − discount_amount
--   • Ảnh demo được lưu local và có nguồn hợp lệ trong docs/image-sources.md; không hotlink internet.
--   • Khoảng cách / walking_minutes của nearby_places là ước lượng DEMO.
-- ═════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE yang_homestay;

-- ═════════════════════════════════════════════════════════════════════════
-- RESET (chỉ trong database yang_homestay)
-- ═════════════════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE login_logs;
TRUNCATE TABLE contact_messages;
TRUNCATE TABLE notifications;
TRUNCATE TABLE nearby_places;
TRUNCATE TABLE reviews;
TRUNCATE TABLE favorites;
TRUNCATE TABLE promotion_usages;
TRUNCATE TABLE booking_price_items;
TRUNCATE TABLE booking_nights;
TRUNCATE TABLE bookings;
TRUNCATE TABLE availability_blocks;
TRUNCATE TABLE promotions;
TRUNCATE TABLE rate_rules;
TRUNCATE TABLE unit_experience_tags;
TRUNCATE TABLE experience_tags;
TRUNCATE TABLE unit_amenities;
TRUNCATE TABLE amenities;
TRUNCATE TABLE unit_images;
TRUNCATE TABLE units;
TRUNCATE TABLE property_images;
TRUNCATE TABLE properties;
TRUNCATE TABLE customers;
TRUNCATE TABLE admins;
SET FOREIGN_KEY_CHECKS = 1;

-- ═════════════════════════════════════════════════════════════════════════
-- 1. ADMINS (3) — password bcrypt, tương thích password_hash()
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO admins (id, username, email, password_hash, full_name, role, status) VALUES
(1, 'admin',   'admin@yanghomestay.com',   '$2y$10$QfOumZHM5rSTdRpaya1cgO4ikV4NICu9G/G5TcVSt62q81VLZ.TIG', 'Quản trị viên hệ thống', 'super_admin', 'active'),
(2, 'manager', 'manager@yanghomestay.com', '$2y$10$cHUdmgUYh60J6bfj9NUMEeBdkvI.s4LJWHhRc09geZcYBL9Mibz9.', 'Quản lý vận hành',       'manager',      'active'),
(3, 'staff',   'staff@yanghomestay.com',   '$2y$10$q6Fw8/ErZC16grgrrvI9au/mCQEb/Pksw8o.mqr6mfWqpZN9fbgme', 'Nhân viên lễ tân',       'staff',        'active');

-- ═════════════════════════════════════════════════════════════════════════
-- 2. CUSTOMERS (8) — tất cả dùng password demo: customer123
--    Email *.example.com là địa chỉ demo chuẩn (RFC 2606), không gửi được thư thật.
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO customers (id, full_name, email, phone, password_hash, status, email_verified_at, created_at) VALUES
(1, 'Nguyễn Minh Anh',  'minh.anh@example.com',   '0901234567', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-06-01 10:00:00', '2026-06-01 10:00:00'),
(2, 'Trần Thu Hà',      'thu.ha@example.com',     '0912345678', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-06-05 09:00:00', '2026-06-05 09:00:00'),
(3, 'Lê Hoàng Nam',     'hoang.nam@example.com',  '0923456789', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-06-10 14:00:00', '2026-06-10 14:00:00'),
(4, 'Phạm Quỳnh Trang', 'quynh.trang@example.com','0934567890', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-06-12 08:00:00', '2026-06-12 08:00:00'),
(5, 'Vũ Đức Huy',       'duc.huy@example.com',    '0945678901', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-06-15 11:00:00', '2026-06-15 11:00:00'),
(6, 'Đặng Thị Mai',     'thi.mai@example.com',    '0956789012', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-07-01 10:00:00', '2026-07-01 10:00:00'),
(7, 'Bùi Quang Minh',   'quang.minh@example.com', '0967890123', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-07-10 15:00:00', '2026-07-10 15:00:00'),
(8, 'Hoàng Thu Phương', 'thu.phuong@example.com', '0978901234', '$2y$10$FQCb8etOxitUQH8HQ5kanuZjgmGC9CBunf93dDcshslCNui/hrVQ6', 'active', '2026-07-20 09:00:00', '2026-07-20 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 3. PROPERTIES (5) — địa chỉ ở mức district/neighborhood (DEMO)
--    Latitude/longitude là tọa độ gần đúng khu vực (DEMO).
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO properties (id, code, name, slug, district, ward, address, latitude, longitude, short_description, description, story, checkin_time, checkout_time, status, featured, created_at) VALUES
(1, 'YH-PC', 'YangHomestay Phố Cổ', 'yanghomestay-pho-co',
 'Hoàn Kiếm', 'Hàng Bạc', 'Khu vực Phố Cổ, Hoàn Kiếm, Hà Nội',
 21.0333333, 105.8500000,
 'Homestay giữa lòng phố cổ, cách Hồ Gươm vài phút đi bộ.',
 'Những căn hộ ấm cúng nằm trong khu phố cổ 36 phố phường. Khách dễ dàng đi bộ ra Hồ Gươm, chợ đêm cuối tuần và thưởng thức ẩm thực đường phố đặc trưng Hà Nội.',
 'YangHomestay khởi đầu từ một căn studio nhỏ ở phố cổ với mong muốn mang đến chỗ ở "đúng gu" cho khách yêu Hà Nội: gần gũi, có hồn và đầy đủ tiện nghi.',
 '14:00:00', '12:00:00', 'active', 1, '2026-05-01 09:00:00'),
(2, 'YH-TB', 'YangHomestay Trúc Bạch', 'yanghomestay-truc-bach',
 'Ba Đình', 'Trúc Bạch', 'Khu vực Trúc Bạch, Ba Đình, Hà Nội',
 21.0458333, 105.8391667,
 'Căn hộ yên tĩnh cạnh hồ Trúc Bạch, view hồ thoáng mát.',
 'Khu vực hồ Trúc Bạch nổi tiếng yên bình, gần Hồ Tây và phố cổ. Phù hợp khách muốn nghỉ dưỡng giữa lòng thành phố nhưng vẫn cần sự tĩnh lặng.',
 'Chúng tôi chọn Trúc Bạch vì nhịp sống chậm rãi bên hồ — nơi khách có thể vừa tận hưởng Hà Nội cổ kính, vừa có không gian riêng tư.',
 '14:00:00', '12:00:00', 'active', 1, '2026-05-03 09:00:00'),
(3, 'YH-TH', 'YangHomestay Tây Hồ', 'yanghomestay-tay-ho',
 'Tây Hồ', 'Quảng An', 'Khu vực Hồ Tây, Tây Hồ, Hà Nội',
 21.0708333, 105.8150000,
 'Hoàng hôn trên hồ Tây, không gian lãng mạn và sáng tạo.',
 'Khu vực ven hồ Tây với nhiều quán cà phê, nhà hàng và không gian nghệ thuật. Hoàng hôn xuống hồ là trải nghiệm khó quên của bất kỳ ai ghé thăm.',
 'Tây Hồ là nơi Hà Nội hiện đại gặp gỡ thiên nhiên. Mỗi căn hộ của chúng tôi ở đây đều hướng tới trải nghiệm thư giãn và sáng tạo.',
 '14:00:00', '12:00:00', 'active', 1, '2026-05-05 09:00:00'),
(4, 'YH-DD', 'YangHomestay Đống Đa', 'yanghomestay-dong-da',
 'Đống Đa', 'Ô Chợ Dừa', 'Khu vực Đống Đa, Hà Nội',
 21.0194444, 105.8277778,
 'Nơi ở tiện lợi và tiết kiệm, gần Văn Miếu và đường tàu.',
 'Quận Đống Đa sầm uất với nhiều hàng quán bình dân, gần Văn Miếu – Quốc Tử Giám và đoạn đường tàu nổi tiếng. Lựa chọn hợp lý cho khách muốn trải nghiệm Hà Nội đời thường.',
 'Đống Đa đại diện cho Hà Nội giản dị và chân thật nhất — nơi chúng tôi muốn khách cảm nhận nhịp sống địa phương thực sự.',
 '14:00:00', '12:00:00', 'active', 0, '2026-05-08 09:00:00'),
(5, 'YH-CG', 'YangHomestay Cầu Giấy', 'yanghomestay-cau-giay',
 'Cầu Giấy', 'Dịch Vọng', 'Khu vực Cầu Giấy, Hà Nội',
 21.0319444, 105.7986111,
 'Hiện đại và tiện nghi, gần khu văn phòng và trung tâm hội nghị.',
 'Khu Cầu Giấy phát triển nhanh với nhiều tòa văn phòng, trung tâm thương mại. Thích hợp cho khách công tác và các bạn trẻ đi làm.',
 'Cầu Giấy là nơi dành cho khách bận rộn: căn hộ hiện đại, check-in nhanh, không gian làm việc ổn định giữa lòng thành phố đang phát triển.',
 '14:00:00', '12:00:00', 'active', 0, '2026-05-10 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 4. PROPERTY_IMAGES (15) — ảnh thật/stock local đã curate, không hotlink
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO property_images (id, property_id, image_url, caption, image_category, display_order, is_cover, created_at) VALUES
(1, 1, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Không gian khu Phố Cổ', 'exterior', 0, 1, '2026-05-15 10:00:00'),
(2, 1, 'assets/images/homestays/old-quarter-balcony-studio/cover.jpg', 'Không gian lưu trú tại Phố Cổ', 'common_area', 1, 0, '2026-05-15 10:00:00'),
(3, 1, 'assets/images/homestays/hanoi-vintage-loft/cover.jpg', 'Nội thất chọn lọc tại Phố Cổ', 'common_area', 2, 0, '2026-05-15 10:00:00'),
(4, 2, 'assets/images/homestays/truc-bach-lake-view-studio/window-view.jpg', 'Góc nhìn thoáng tại Trúc Bạch', 'view', 0, 1, '2026-05-15 10:00:00'),
(5, 2, 'assets/images/homestays/truc-bach-lake-view-studio/cover.jpg', 'Không gian lưu trú bên hồ Trúc Bạch', 'common_area', 1, 0, '2026-05-15 10:00:00'),
(6, 2, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Ban công xanh tại Trúc Bạch', 'balcony', 2, 0, '2026-05-15 10:00:00'),
(7, 3, 'assets/images/homestays/west-lake-sunset-studio/lake-view.jpg', 'Góc nhìn Hồ Tây', 'view', 0, 1, '2026-05-15 10:00:00'),
(8, 3, 'assets/images/homestays/west-lake-sunset-studio/cover.jpg', 'Không gian lưu trú tại Hồ Tây', 'common_area', 1, 0, '2026-05-15 10:00:00'),
(9, 3, 'assets/images/homestays/west-lake-workcation-loft/cover.jpg', 'Không gian workcation tại Hồ Tây', 'common_area', 2, 0, '2026-05-15 10:00:00'),
(10, 4, 'assets/images/homestays/dong-da-garden-apartment/cover.jpg', 'Không gian lưu trú tại Đống Đa', 'common_area', 0, 1, '2026-05-15 10:00:00'),
(11, 4, 'assets/images/homestays/dong-da-cozy-studio/cover.jpg', 'Studio ấm cúng tại Đống Đa', 'common_area', 1, 0, '2026-05-15 10:00:00'),
(12, 4, 'assets/images/homestays/dong-da-urban-loft/cover.jpg', 'Loft đô thị tại Đống Đa', 'common_area', 2, 0, '2026-05-15 10:00:00'),
(13, 5, 'assets/images/homestays/cau-giay-family-suite/cover.jpg', 'Không gian căn hộ tại Cầu Giấy', 'common_area', 0, 1, '2026-05-15 10:00:00'),
(14, 5, 'assets/images/homestays/cau-giay-modern-studio/cover.jpg', 'Studio hiện đại tại Cầu Giấy', 'common_area', 1, 0, '2026-05-15 10:00:00'),
(15, 5, 'assets/images/homestays/cau-giay-business-apartment/cover.jpg', 'Căn hộ công tác tại Cầu Giấy', 'common_area', 2, 0, '2026-05-15 10:00:00');

-- 5. UNITS (16) — giá demo 550.000 → 2.500.000 VND/đêm
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO units (id, property_id, code, name, slug, type, base_price, cleaning_fee, max_guests, bedrooms, beds, bathrooms, area_m2, short_description, description, house_rules, status, featured, created_at) VALUES
(1, 1, 'YH-PC-01', 'Old Quarter Balcony Studio', 'old-quarter-balcony-studio', 'studio', 850000, 100000, 2, 1, 1, 1, 28,
 'Studio có ban công nhìn ra phố cổ, đủ ấm cúng cho cặp đôi.',
 'Studio 28m² với ban công nhỏ ngắm phố cổ. Giường đôi thoải mái, khu vực trà cà phê và góc đọc sách.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h. Không tiệc tùng.', 'active', 1, '2026-05-01 10:00:00'),
(2, 1, 'YH-PC-02', 'Hanoi Vintage Loft', 'hanoi-vintage-loft', 'loft', 1050000, 100000, 2, 1, 1, 1, 35,
 'Gác xép cổ điển cho 2 khách, nội thất gỗ và trần cao.',
 'Loft 2 tầng nhỏ với trần cao, gạch cổ và nội thất gỗ. Gác xép có 1 giường đôi cho tối đa 2 khách; tầng dưới là bếp nhỏ và bàn làm việc.',
 'Không hút thuốc trong phòng. Không mang giày vào trong. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-01 10:00:00'),
(3, 1, 'YH-PC-03', 'Hoan Kiem Couple Studio', 'hoan-kiem-couple-studio', 'couple', 950000, 100000, 2, 1, 1, 1, 30,
 'Studio lãng mạn với bồn tắm, cách Hồ Gươm vài phút đi bộ.',
 'Studio thiết kế riêng cho cặp đôi: bồn tắm, đèn ấm và ban công nhỏ. Vị trí trung tâm thuận tiện dạo phố đi bộ.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 1, '2026-05-01 10:00:00'),
(4, 2, 'YH-TB-01', 'Truc Bach Lake View Studio', 'truc-bach-lake-view-studio', 'studio', 1200000, 120000, 2, 1, 1, 1, 32,
 'Studio view hồ Trúc Bạch, bình minh và hoàng hôn đều đẹp.',
 'Cửa sổ lớn nhìn thẳng ra hồ Trúc Bạch. Ban công nhỏ uống cà phê sáng, bồn tắm thư giãn cuối ngày.',
 'Không hút thuốc trong phòng. Không tiệc tùng. Giữ yên tĩnh sau 22h.', 'active', 1, '2026-05-03 10:00:00'),
(5, 2, 'YH-TB-02', 'Truc Bach Balcony Room', 'truc-bach-balcony-room', 'couple', 1100000, 100000, 2, 1, 1, 1, 26,
 'Phòng ban công nhìn hồ, không gian ấm cúng giá hợp lý.',
 'Phòng nhỏ gọn nhưng đủ đầy với ban công ngắm hồ, giường đôi và khu vực trà cà phê.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-03 10:00:00'),
(6, 2, 'YH-TB-03', 'Ba Dinh Family Apartment', 'ba-dinh-family-apartment', 'family', 2200000, 200000, 4, 2, 3, 2, 70,
 'Căn hộ 2 phòng ngủ rộng rãi cho gia đình tối đa 4 khách.',
 'Căn hộ 70m² với 2 phòng ngủ, 3 giường (1 giường đôi + 2 giường đơn), bếp đầy đủ dụng cụ, máy giặt và ban công. Phù hợp gia đình tối đa 4 người ở dài ngày.',
 'Không hút thuốc trong phòng. Không tiệc tùng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-03 10:00:00'),
(7, 3, 'YH-TH-01', 'West Lake Sunset Studio', 'west-lake-sunset-studio', 'studio', 1500000, 150000, 2, 1, 1, 1, 34,
 'Studio ngắm hoàng hôn hồ Tây, cửa sổ kính lớn.',
 'Studio hướng tây với cửa sổ kính lớn đón trọn hoàng hôn hồ Tây. Bồn tắm và ban công riêng.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 1, '2026-05-05 10:00:00'),
(8, 3, 'YH-TH-02', 'West Lake Workcation Loft', 'west-lake-workcation-loft', 'loft', 1800000, 150000, 2, 1, 1, 1, 40,
 'Loft view hồ cho 1-2 khách, có góc làm việc riêng và wifi mạnh.',
 'Loft 40m² cho tối đa 2 khách với 1 giường, bàn làm việc riêng, wifi tốc độ cao, máy chiếu và view hồ Tây thư giãn.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h. Vui lòng báo trước nếu tổ chức họp trực tuyến buổi tối.', 'active', 1, '2026-05-05 10:00:00'),
(9, 3, 'YH-TH-03', 'West Lake Family Apartment', 'west-lake-family-apartment', 'family', 2500000, 250000, 4, 2, 3, 2, 85,
 'Căn hộ gia đình view hồ Tây, 2 phòng ngủ cho tối đa 4 khách.',
 'Căn hộ 85m² với 2 phòng ngủ, 3 giường (1 giường đôi + 2 giường đơn), phòng khách rộng, bếp đầy đủ và ban công dài ngắm hồ. Phù hợp gia đình tối đa 4 người.',
 'Không hút thuốc trong phòng. Không tiệc tùng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-05 10:00:00'),
(10, 4, 'YH-DD-01', 'Dong Da Cozy Studio', 'dong-da-cozy-studio', 'studio', 650000, 80000, 2, 1, 1, 1, 24,
 'Studio nhỏ gọn giá mềm, gần đường tàu Hà Nội.',
 'Studio 24m² đủ tiện nghi cơ bản với giường đôi và khu vực bếp nhỏ. Giá hợp lý cho khách du lịch tiết kiệm.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-08 10:00:00'),
(11, 4, 'YH-DD-02', 'Dong Da Urban Loft', 'dong-da-urban-loft', 'loft', 900000, 80000, 2, 1, 1, 1, 33,
 'Loft trẻ trung cho tối đa 2 khách, có gác ngủ và máy chiếu.',
 'Loft hiện đại với 1 giường ở gác ngủ, máy chiếu xem phim và góc làm việc. Phù hợp khách đi một mình hoặc hai người.',
 'Không hút thuốc trong phòng. Không tiệc tùng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-08 10:00:00'),
(12, 4, 'YH-DD-03', 'Dong Da Garden Apartment', 'dong-da-garden-apartment', 'apartment', 1400000, 150000, 3, 1, 2, 1, 50,
 'Căn hộ có ban công xanh cho tối đa 3 khách, có sofa bed.',
 'Căn hộ 50m² với 1 giường chính và 1 sofa bed, ban công trồng cây, bếp đầy đủ và máy giặt. Phù hợp gia đình nhỏ tối đa 3 khách.',
 'Không hút thuốc trong phòng. Không tiệc tùng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-08 10:00:00'),
(13, 5, 'YH-CG-01', 'Cau Giay Modern Studio', 'cau-giay-modern-studio', 'studio', 700000, 80000, 2, 1, 1, 1, 26,
 'Studio hiện đại có thang máy, vị trí thuận tiện đi làm.',
 'Studio 26m² nội thất hiện đại, có thang máy; quầy bar nhỏ có thể dùng làm bàn làm việc. Phù hợp khách công tác ngắn ngày.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-10 10:00:00'),
(14, 5, 'YH-CG-02', 'Cau Giay Business Apartment', 'cau-giay-business-apartment', 'apartment', 1200000, 100000, 2, 1, 1, 1, 42,
 'Căn hộ 1 phòng ngủ cho 1-2 khách công tác, gần khu văn phòng.',
 'Căn hộ 42m² cho tối đa 2 khách với 1 giường, phòng ngủ riêng, bàn làm việc rộng, bếp và máy giặt. Gần nhiều tòa văn phòng tại Cầu Giấy.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 1, '2026-05-10 10:00:00'),
(15, 5, 'YH-CG-03', 'Cau Giay Corner Studio', 'cau-giay-corner-studio', 'studio', 800000, 80000, 2, 1, 1, 1, 27,
 'Studio góc có ban công nhỏ, sáng sủa và thoáng.',
 'Studio 27m² ở góc tòa nhà với ban công nhỏ đón sáng. Nội thất tối giản, sạch sẽ.',
 'Không hút thuốc trong phòng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-10 10:00:00'),
(16, 5, 'YH-CG-04', 'Cau Giay Family Suite', 'cau-giay-family-suite', 'family', 1600000, 150000, 4, 2, 3, 2, 60,
 'Suite 2 phòng ngủ cho gia đình, đầy đủ tiện nghi.',
 'Suite 60m² với 2 phòng ngủ, tổng 3 giường, 2 nhà tắm, bếp và ban công. Phù hợp gia đình tối đa 4 người hoặc nhóm bạn.',
 'Không hút thuốc trong phòng. Không tiệc tùng. Giữ yên tĩnh sau 22h.', 'active', 0, '2026-05-10 10:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 6. UNIT_IMAGES (62) — gallery local curate theo sức chứa, tránh lặp ảnh khác khu vực
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO unit_images (id, unit_id, image_url, caption, image_category, display_order, is_cover, created_at) VALUES
(1, 1, 'assets/images/homestays/old-quarter-balcony-studio/cover.jpg', 'Old Quarter Balcony Studio — Không gian sinh hoạt vintage', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(2, 1, 'assets/images/homestays/old-quarter-balcony-studio/bedroom.jpg', 'Old Quarter Balcony Studio — Giường đôi', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(3, 1, 'assets/images/homestays/old-quarter-balcony-studio/balcony.jpg', 'Old Quarter Balcony Studio — Ban công riêng', 'balcony', 2, 0, '2026-08-17 13:01:00'),
(4, 1, 'assets/images/homestays/old-quarter-balcony-studio/bathroom.jpg', 'Old Quarter Balcony Studio — Phòng tắm phong cách vintage', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(5, 2, 'assets/images/homestays/hanoi-vintage-loft/cover.jpg', 'Hanoi Vintage Loft — Không gian loft', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(6, 2, 'assets/images/homestays/hanoi-vintage-loft/bedroom.jpg', 'Hanoi Vintage Loft — Giường đôi trên gác', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(7, 2, 'assets/images/homestays/hanoi-vintage-loft/bathroom.jpg', 'Hanoi Vintage Loft — Phòng tắm phong cách vintage', 'bathroom', 2, 0, '2026-08-17 13:01:00'),
(8, 3, 'assets/images/homestays/hoan-kiem-couple-studio/cover.jpg', 'Hoan Kiem Couple Studio — Phòng ngủ chính', 'bedroom', 0, 1, '2026-08-17 13:01:00'),
(9, 3, 'assets/images/homestays/hoan-kiem-couple-studio/bedroom-2.jpg', 'Hoan Kiem Couple Studio — Góc nghỉ sáng tự nhiên', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(10, 3, 'assets/images/homestays/hoan-kiem-couple-studio/bathroom.jpg', 'Hoan Kiem Couple Studio — Bồn tắm thư giãn', 'bathroom', 2, 0, '2026-08-17 13:01:00'),
(11, 4, 'assets/images/homestays/truc-bach-lake-view-studio/cover.jpg', 'Truc Bach Lake View Studio — Không gian bên hồ', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(12, 4, 'assets/images/homestays/truc-bach-lake-view-studio/bedroom.jpg', 'Truc Bach Lake View Studio — Giường ngủ sáng tự nhiên', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(13, 4, 'assets/images/homestays/truc-bach-lake-view-studio/lake-view.jpg', 'Truc Bach Lake View Studio — Mặt hồ Trúc Bạch và thuyền thiên nga', 'view', 2, 0, '2026-08-17 13:01:00'),
(14, 4, 'assets/images/homestays/truc-bach-lake-view-studio/bathroom.jpg', 'Truc Bach Lake View Studio — Phòng tắm có bồn', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(15, 5, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Truc Bach Balcony Room — Ban công nhiều cây xanh', 'balcony', 0, 1, '2026-08-17 13:01:00'),
(16, 5, 'assets/images/homestays/truc-bach-balcony-room/bedroom.jpg', 'Truc Bach Balcony Room — Giường đôi', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(17, 5, 'assets/images/homestays/truc-bach-balcony-room/bathroom.jpg', 'Truc Bach Balcony Room — Phòng tắm', 'bathroom', 2, 0, '2026-08-17 13:01:00'),
(18, 6, 'assets/images/homestays/ba-dinh-family-apartment/cover.jpg', 'Ba Dinh Family Apartment — Phòng khách và bếp', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(19, 6, 'assets/images/homestays/ba-dinh-family-apartment/bedroom.jpg', 'Ba Dinh Family Apartment — Phòng ngủ chính, giường đôi', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(20, 6, 'assets/images/homestays/ba-dinh-family-apartment/second-bedroom.jpg', 'Ba Dinh Family Apartment — Phòng ngủ thứ hai, 2 giường đơn', 'bedroom', 2, 0, '2026-08-17 13:01:00'),
(21, 6, 'assets/images/homestays/ba-dinh-family-apartment/bathroom.jpg', 'Ba Dinh Family Apartment — Phòng tắm', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(22, 7, 'assets/images/homestays/west-lake-sunset-studio/cover.jpg', 'West Lake Sunset Studio — Không gian nhìn ra hồ', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(23, 7, 'assets/images/homestays/west-lake-sunset-studio/bedroom.jpg', 'West Lake Sunset Studio — Giường ngủ', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(24, 7, 'assets/images/homestays/premium-bathroom/bathtub.jpg', 'West Lake Sunset Studio — Bồn tắm', 'bathroom', 2, 0, '2026-08-17 13:01:00'),
(25, 8, 'assets/images/homestays/west-lake-workcation-loft/cover.jpg', 'West Lake Workcation Loft — Không gian view hồ', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(26, 8, 'assets/images/homestays/west-lake-workcation-loft/bedroom-workspace.jpg', 'West Lake Workcation Loft — Giường và bàn làm việc', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(27, 8, 'assets/images/homestays/west-lake-workcation-loft/workspace.jpg', 'West Lake Workcation Loft — Góc làm việc riêng', 'workspace', 2, 0, '2026-08-17 13:01:00'),
(28, 8, 'assets/images/homestays/west-lake-workcation-loft/bathroom.jpg', 'West Lake Workcation Loft — Phòng tắm hiện đại', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(29, 9, 'assets/images/homestays/west-lake-family-apartment/cover.jpg', 'West Lake Family Apartment — Phòng khách và bếp', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(30, 9, 'assets/images/homestays/west-lake-family-apartment/bedroom.jpg', 'West Lake Family Apartment — Phòng ngủ chính', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(31, 9, 'assets/images/homestays/west-lake-family-apartment/second-bedroom.jpg', 'West Lake Family Apartment — Phòng ngủ thứ hai, 2 giường đơn', 'bedroom', 2, 0, '2026-08-17 13:01:00'),
(32, 9, 'assets/images/homestays/experiences/west-lake-view.jpg', 'West Lake Family Apartment — Hồ Tây trong khu vực lưu trú', 'other', 3, 0, '2026-08-17 13:01:00'),
(33, 9, 'assets/images/homestays/west-lake-family-apartment/bathroom.jpg', 'West Lake Family Apartment — Phòng tắm có bồn', 'bathroom', 4, 0, '2026-08-17 13:01:00'),
(34, 10, 'assets/images/homestays/dong-da-cozy-studio/cover.jpg', 'Dong Da Cozy Studio — Không gian chính ấm áp', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(35, 10, 'assets/images/homestays/dong-da-cozy-studio/bedroom.jpg', 'Dong Da Cozy Studio — Giường đôi trong không gian ấm cúng', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(36, 10, 'assets/images/homestays/dong-da-cozy-studio/bathroom.jpg', 'Dong Da Cozy Studio — Phòng tắm', 'bathroom', 2, 0, '2026-08-17 13:01:00'),
(37, 11, 'assets/images/homestays/dong-da-urban-loft/cover.jpg', 'Dong Da Urban Loft — Không gian loft và khu ngủ', 'bedroom', 0, 1, '2026-08-17 13:01:00'),
(38, 11, 'assets/images/homestays/dong-da-urban-loft/workspace.jpg', 'Dong Da Urban Loft — Góc làm việc sáng tự nhiên', 'workspace', 1, 0, '2026-08-17 13:01:00'),
(39, 11, 'assets/images/homestays/dong-da-urban-loft/bathroom.jpg', 'Dong Da Urban Loft — Phòng tắm', 'bathroom', 2, 0, '2026-08-17 13:01:00'),
(40, 12, 'assets/images/homestays/dong-da-garden-apartment/cover.jpg', 'Dong Da Garden Apartment — Phòng khách', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(41, 12, 'assets/images/homestays/dong-da-garden-apartment/bedroom.jpg', 'Dong Da Garden Apartment — Giường chính', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(42, 12, 'assets/images/homestays/dong-da-garden-apartment/sofa-bed.jpg', 'Dong Da Garden Apartment — Sofa bed cho khách thứ ba', 'living_room', 2, 0, '2026-08-17 13:01:00'),
(43, 12, 'assets/images/homestays/dong-da-garden-apartment/balcony.jpg', 'Dong Da Garden Apartment — Ban công xanh và view thành phố', 'balcony', 3, 0, '2026-08-17 13:01:00'),
(44, 12, 'assets/images/homestays/dong-da-garden-apartment/bathroom.jpg', 'Dong Da Garden Apartment — Phòng tắm', 'bathroom', 4, 0, '2026-08-17 13:01:00'),
(45, 13, 'assets/images/homestays/cau-giay-modern-studio/cover.jpg', 'Cau Giay Modern Studio — Không gian sinh hoạt', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(46, 13, 'assets/images/homestays/cau-giay-modern-studio/bedroom.jpg', 'Cau Giay Modern Studio — Giường đôi và quầy bếp studio', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(48, 13, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Cau Giay Modern Studio — Phòng tắm', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(49, 14, 'assets/images/homestays/cau-giay-business-apartment/cover.jpg', 'Cau Giay Business Apartment — Phòng khách và bếp', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(50, 14, 'assets/images/homestays/cau-giay-business-apartment/bedroom.jpg', 'Cau Giay Business Apartment — Phòng ngủ và giường chính', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(51, 14, 'assets/images/homestays/cau-giay-business-apartment/kitchen.jpg', 'Cau Giay Business Apartment — Bếp', 'kitchen', 2, 0, '2026-08-17 13:01:00'),
(52, 14, 'assets/images/homestays/cau-giay-business-apartment/bathroom.jpg', 'Cau Giay Business Apartment — Phòng tắm', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(53, 14, 'assets/images/homestays/cau-giay-business-apartment/living-room.jpg', 'Cau Giay Business Apartment — Góc phòng khách', 'living_room', 4, 0, '2026-08-17 13:01:00'),
(54, 14, 'assets/images/homestays/cau-giay-business-apartment/workspace.jpg', 'Cau Giay Business Apartment — Góc làm việc', 'workspace', 5, 0, '2026-08-17 13:01:00'),
(55, 15, 'assets/images/homestays/cau-giay-corner-studio/cover.jpg', 'Cau Giay Corner Studio — Không gian sinh hoạt sáng', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(56, 15, 'assets/images/homestays/cau-giay-corner-studio/bedroom.jpg', 'Cau Giay Corner Studio — Giường đôi và quầy bếp studio', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(57, 15, 'assets/images/homestays/cau-giay-corner-studio/balcony.jpg', 'Cau Giay Corner Studio — Ban công và góc ngồi', 'balcony', 2, 0, '2026-08-17 13:01:00'),
(58, 15, 'assets/images/homestays/cau-giay-corner-studio/bathroom.jpg', 'Cau Giay Corner Studio — Phòng tắm', 'bathroom', 3, 0, '2026-08-17 13:01:00'),
(59, 16, 'assets/images/homestays/cau-giay-family-suite/cover.jpg', 'Cau Giay Family Suite — Phòng khách và bếp', 'living_room', 0, 1, '2026-08-17 13:01:00'),
(60, 16, 'assets/images/homestays/cau-giay-family-suite/bedroom.jpg', 'Cau Giay Family Suite — Phòng ngủ chính', 'bedroom', 1, 0, '2026-08-17 13:01:00'),
(61, 16, 'assets/images/homestays/cau-giay-family-suite/second-bedroom.jpg', 'Cau Giay Family Suite — Phòng ngủ thứ hai với 2 giường', 'bedroom', 2, 0, '2026-08-17 13:01:00'),
(62, 16, 'assets/images/homestays/cau-giay-family-suite/bathroom.jpg', 'Cau Giay Family Suite — Phòng tắm', 'bathroom', 3, 0, '2026-08-17 13:01:00');

-- 7. AMENITIES (18) — danh mục tiện nghi dùng chung
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO amenities (id, code, name_vi, name_en, icon, category, active) VALUES
(1,  'wifi',             'WiFi tốc độ cao',   'High-speed WiFi',     'wifi',     'essential',     1),
(2,  'air_conditioner',  'Điều hòa',          'Air conditioning',    'ac',       'essential',     1),
(3,  'smart_tv',         'Smart TV',          'Smart TV',            'tv',       'entertainment', 1),
(4,  'kitchen',          'Bếp',               'Kitchen',             'kitchen',  'kitchen',       1),
(5,  'refrigerator',     'Tủ lạnh',           'Refrigerator',        'fridge',   'kitchen',       1),
(6,  'washing_machine',  'Máy giặt',          'Washing machine',     'washer',   'essential',     1),
(7,  'hair_dryer',       'Máy sấy tóc',       'Hair dryer',          'dryer',    'bathroom',      1),
(8,  'water_heater',     'Bình nóng lạnh',    'Water heater',        'heater',   'bathroom',      1),
(9,  'bathtub',          'Bồn tắm',           'Bathtub',             'bathtub',  'bathroom',      1),
(10, 'balcony',          'Ban công',          'Balcony',             'balcony',  'outdoor',       1),
(11, 'lake_view',        'View hồ',           'Lake view',           'lake',     'view',          1),
(12, 'workspace',        'Bàn làm việc',      'Workspace',           'desk',     'workspace',     1),
(13, 'parking',          'Chỗ đỗ xe',         'Parking',             'parking',  'parking',       1),
(14, 'elevator',         'Thang máy',         'Elevator',            'elevator', 'accessibility', 1),
(15, 'projector',        'Máy chiếu',         'Projector',           'projector','entertainment', 1),
(16, 'microwave',        'Lò vi sóng',        'Microwave',           'microwave','kitchen',       1),
(17, 'kettle',           'Ấm đun nước',       'Electric kettle',     'kettle',   'kitchen',       1),
(18, 'iron',             'Bàn là',            'Iron',                'iron',     'essential',     1);

-- ═════════════════════════════════════════════════════════════════════════
-- 8. UNIT_AMENITIES — gắn tiện nghi khác nhau theo "gu" của từng unit
--    (couple: bathtub/balcony/smart_tv • workcation: workspace/washing_machine
--     family: kitchen/refrigerator/nhiều giường)
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO unit_amenities (unit_id, amenity_id) VALUES
(1, 1),(1, 2),(1, 3),(1, 10),(1, 7),(1, 8),(1, 17),(1, 18),
(2, 1),(2, 2),(2, 3),(2, 4),(2, 5),(2, 8),(2, 17),(2, 12),(2, 18),
(3, 1),(3, 2),(3, 3),(3, 9),(3, 10),(3, 7),(3, 8),(3, 17),
(4, 1),(4, 2),(4, 3),(4, 11),(4, 10),(4, 9),(4, 8),(4, 7),(4, 17),(4, 18),
(5, 1),(5, 2),(5, 3),(5, 10),(5, 8),(5, 7),(5, 18),
(6, 1),(6, 2),(6, 3),(6, 4),(6, 5),(6, 6),(6, 8),(6, 10),(6, 13),(6, 14),(6, 16),(6, 17),(6, 18),
(7, 1),(7, 2),(7, 3),(7, 11),(7, 10),(7, 9),(7, 8),(7, 7),(7, 17),
(8, 1),(8, 2),(8, 3),(8, 12),(8, 4),(8, 5),(8, 6),(8, 8),(8, 14),(8, 17),(8, 15),
(9, 1),(9, 2),(9, 3),(9, 4),(9, 5),(9, 6),(9, 11),(9, 10),(9, 13),(9, 14),(9, 16),(9, 17),(9, 18),(9, 9),
(10, 1),(10, 2),(10, 3),(10, 4),(10, 8),(10, 17),
(11, 1),(11, 2),(11, 3),(11, 12),(11, 4),(11, 5),(11, 8),(11, 15),
(12, 1),(12, 2),(12, 3),(12, 4),(12, 5),(12, 6),(12, 10),(12, 13),(12, 8),(12, 16),
(13, 1),(13, 2),(13, 3),(13, 12),(13, 4),(13, 5),(13, 8),(13, 14),(13, 17),
(14, 1),(14, 2),(14, 3),(14, 12),(14, 4),(14, 5),(14, 6),(14, 8),(14, 14),(14, 18),(14, 17),
(15, 1),(15, 2),(15, 3),(15, 10),(15, 8),(15, 7),(15, 17),
(16, 1),(16, 2),(16, 3),(16, 4),(16, 5),(16, 6),(16, 8),(16, 10),(16, 14),(16, 16),(16, 17),(16, 18);

-- ═════════════════════════════════════════════════════════════════════════
-- 9. EXPERIENCE_TAGS (19) — đầu vào cho YangMatch
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO experience_tags (id, code, name_vi, name_en, category, active) VALUES
(1,  'couple',      'Cặp đôi',        'Couple',       'guest_type', 1),
(2,  'family',      'Gia đình',       'Family',       'guest_type', 1),
(3,  'solo',        'Đi một mình',    'Solo',         'guest_type', 1),
(4,  'friends',     'Nhóm bạn',       'Friends',      'guest_type', 1),
(5,  'business',    'Công tác',       'Business',     'guest_type', 1),
(6,  'workcation',  'Workcation',     'Workcation',   'work',       1),
(7,  'quiet',       'Yên tĩnh',       'Quiet',        'style',      1),
(8,  'romantic',    'Lãng mạn',       'Romantic',     'style',      1),
(9,  'vintage',     'Cổ điển',        'Vintage',      'style',      1),
(10, 'modern',      'Hiện đại',       'Modern',       'style',      1),
(11, 'luxury',      'Sang trọng',     'Luxury',       'style',      1),
(12, 'budget',      'Tiết kiệm',      'Budget',       'style',      1),
(13, 'food-lover',  'Mê ẩm thực',     'Food lover',   'activity',   1),
(14, 'nightlife',   'Sống về đêm',    'Nightlife',    'activity',   1),
(15, 'photography', 'Nhiếp ảnh',      'Photography',  'activity',   1),
(16, 'lake-view',   'View hồ',        'Lake view',    'location',   1),
(17, 'old-quarter', 'Phố cổ',         'Old Quarter',  'location',   1),
(18, 'long-stay',   'Ở dài ngày',     'Long stay',    'activity',   1),
(19, 'local-life',  'Sống địa phương','Local life',   'activity',   1);

-- ═════════════════════════════════════════════════════════════════════════
-- 10. UNIT_EXPERIENCE_TAGS — weight 1-10 (dữ liệu đầu vào YangMatch)
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO unit_experience_tags (unit_id, tag_id, weight) VALUES
-- Phố Cổ
(1, 1, 10), (1, 17, 10), (1, 13, 9), (1, 14, 7), (1, 15, 8), (1, 9, 8), (1, 8, 7),
(2, 9, 10), (2, 17, 10), (2, 15, 8), (2, 1, 8), (2, 13, 8), (2, 8, 7), (2, 7, 6),
(3, 1, 10), (3, 8, 9), (3, 7, 7), (3, 17, 9), (3, 13, 8), (3, 15, 7),
-- Trúc Bạch
(4, 16, 10), (4, 8, 9), (4, 1, 10), (4, 7, 8), (4, 11, 7), (4, 15, 8),
(5, 1, 9), (5, 8, 8), (5, 7, 7), (5, 12, 7),
(6, 2, 10), (6, 4, 7), (6, 19, 7), (6, 7, 6), (6, 12, 6),
-- Tây Hồ
(7, 16, 10), (7, 8, 9), (7, 1, 9), (7, 11, 8), (7, 15, 8), (7, 14, 6),
(8, 6, 10), (8, 7, 9), (8, 16, 9), (8, 18, 8), (8, 5, 8), (8, 10, 8),
(9, 2, 10), (9, 11, 8), (9, 18, 7), (9, 7, 7),
-- Đống Đa
(10, 12, 10), (10, 3, 9), (10, 7, 8), (10, 19, 8), (10, 13, 7),
(11, 3, 8), (11, 4, 8), (11, 12, 7), (11, 10, 8), (11, 14, 7), (11, 15, 7),
(12, 2, 9), (12, 4, 8), (12, 12, 7), (12, 19, 8), (12, 13, 7),
-- Cầu Giấy
(13, 12, 9), (13, 3, 9), (13, 10, 8), (13, 5, 7), (13, 7, 7),
(14, 5, 10), (14, 6, 9), (14, 10, 9), (14, 18, 8), (14, 7, 7),
(15, 1, 8), (15, 12, 9), (15, 3, 8), (15, 10, 7), (15, 7, 7),
(16, 2, 10), (16, 4, 8), (16, 18, 8), (16, 10, 7), (16, 7, 6);

-- ═════════════════════════════════════════════════════════════════════════
-- 11. RATE_RULES (7) — đủ để test pricing engine Phase 3
--     day_of_week: 1=CN ... 7=T7 (DAYOFWEEK() của MySQL)
--     NULL start/end = áp dụng không giới hạn ngày
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO rate_rules (id, unit_id, name, start_date, end_date, day_of_week, price, priority, active, created_at) VALUES
(1, 4,  'Cuối tuần – Thứ 6', NULL, NULL, 6, 1500000, 10, 1, '2026-07-01 09:00:00'),
(2, 4,  'Cuối tuần – Thứ 7', NULL, NULL, 7, 1500000, 10, 1, '2026-07-01 09:00:00'),
(3, 13, 'Cuối tuần – Thứ 6', NULL, NULL, 6, 800000,  10, 1, '2026-07-01 09:00:00'),
(4, 3,  'Cuối tuần – Thứ 7', NULL, NULL, 7, 1050000, 10, 1, '2026-07-01 09:00:00'),
(5, 8,  'Cuối tuần – Thứ 6', NULL, NULL, 6, 1950000, 10, 1, '2026-07-01 09:00:00'),
(6, 8,  'Cuối tuần – Thứ 7', NULL, NULL, 7, 1950000, 10, 1, '2026-07-01 09:00:00'),
(7, 1,  'Lễ 2/9 Quốc khánh', '2026-09-01', '2026-09-03', NULL, 1000000, 20, 1, '2026-08-01 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 12. PROMOTIONS (2) — DEMO, có hạn sử dụng rõ ràng
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO promotions (id, code, name, description, discount_type, discount_value, minimum_booking_value, maximum_discount, valid_from, valid_until, usage_limit, usage_limit_per_customer, active, created_at) VALUES
(1, 'YANGNEW10', 'Giảm 10% cho khách mới (DEMO)',
 'DEMO PROMOTION — Giảm 10% giá phòng cho đơn từ 500.000₫, áp dụng 1 lần/khách.',
 'percentage', 10, 500000, NULL, '2026-08-01', '2026-12-31', 100, 1, 1, '2026-08-01 09:00:00'),
(2, 'HANOI150', 'Giảm 150.000₫ cho đơn từ 1.000.000₫ (DEMO)',
 'DEMO PROMOTION — Giảm 150.000₫ cho đơn từ 1.000.000₫, áp dụng 1 lần/khách.',
 'fixed_amount', 150000, 1000000, 150000, '2026-08-01', '2026-12-31', 50, 1, 1, '2026-08-01 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 13. AVAILABILITY_BLOCKS (3) — start inclusive / end exclusive
--     20/08 → 23/08 chặn các đêm 20, 21, 22
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO availability_blocks (id, unit_id, start_date, end_date, reason, note, created_by_admin_id, created_at) VALUES
(1, 11, '2026-08-20', '2026-08-23', 'maintenance', 'Bảo trì điều hòa phòng ngủ', 1, '2026-08-11 10:00:00'),
(2, 9,  '2026-08-25', '2026-08-28', 'owner_use',   'Chủ nhà sử dụng căn hộ',     2, '2026-08-12 14:00:00'),
(3, 1,  '2026-08-17', '2026-08-18', 'cleaning',    'Vệ sinh sâu sau lượt khách', 3, '2026-08-13 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 14. BOOKINGS (12) — dữ liệu coherent:
--     number_of_nights = DATEDIFF(check_out, check_in)
--     subtotal = SUM(booking_nights.nightly_rate)
--     total = subtotal + cleaning_fee − discount_amount
--     Status: 1 pending, 3 confirmed, 2 checkin, 5 checkout, 1 cancelled
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO bookings
(id, booking_reference, customer_id, unit_id, guest_name, guest_email, guest_phone,
 check_in, check_out, number_of_nights, adults, children, total_guests,
 subtotal, cleaning_fee, discount_amount, total_amount, currency,
 promotion_id, promotion_code_snapshot, status, payment_status,
 special_requests, cancellation_reason,
 created_at, confirmed_at, checked_in_at, checked_out_at, cancelled_at) VALUES
-- 1. Đống Đa Cozy Studio — đã hoàn tất (checkout)
(1, 'YH202608010003', 5, 10, 'Vũ Đức Huy', 'duc.huy@example.com', '0945678901',
 '2026-08-01', '2026-08-04', 3, 2, 0, 2,
 1950000, 80000, 0, 2030000, 'VND', NULL, NULL,
 'checkout', 'paid', NULL, NULL,
 '2026-07-25 10:00:00', '2026-07-25 10:30:00', '2026-08-01 14:10:00', '2026-08-04 11:30:00', NULL),
-- 2. Truc Bach Balcony Room — đã hoàn tất (checkout)
(2, 'YH202608030011', 5, 5, 'Vũ Đức Huy', 'duc.huy@example.com', '0945678901',
 '2026-08-03', '2026-08-05', 2, 2, 0, 2,
 2200000, 100000, 0, 2300000, 'VND', NULL, NULL,
 'checkout', 'paid', 'Xin phòng tầng cao.', NULL,
 '2026-07-28 09:00:00', '2026-07-28 09:15:00', '2026-08-03 14:00:00', '2026-08-05 11:00:00', NULL),
-- 3. Truc Bach Lake View Studio — đã hoàn tất (checkout) — 1 đêm cuối tuần (rule 1)
(3, 'YH202608050002', 1, 4, 'Nguyễn Minh Anh', 'minh.anh@example.com', '0901234567',
 '2026-08-05', '2026-08-08', 3, 2, 0, 2,
 3900000, 120000, 0, 4020000, 'VND', NULL, NULL,
 'checkout', 'paid', NULL, NULL,
 '2026-07-30 15:00:00', '2026-07-30 15:20:00', '2026-08-05 14:30:00', '2026-08-08 12:00:00', NULL),
-- 4. West Lake Workcation Loft — đã hoàn tất (checkout) — 2 đêm cuối tuần (rules 5, 6)
(4, 'YH202608060013', 4, 8, 'Phạm Quỳnh Trang', 'quynh.trang@example.com', '0934567890',
 '2026-08-06', '2026-08-09', 3, 2, 0, 2,
 5700000, 150000, 0, 5850000, 'VND', NULL, NULL,
 'checkout', 'paid', 'Cần bàn làm việc ổn định.', NULL,
 '2026-07-29 11:00:00', '2026-07-29 11:30:00', '2026-08-06 15:00:00', '2026-08-09 11:45:00', NULL),
-- 5. Old Quarter Balcony Studio — đã hoàn tất (checkout)
(5, 'YH202608100001', 3, 1, 'Lê Hoàng Nam', 'hoang.nam@example.com', '0923456789',
 '2026-08-10', '2026-08-13', 3, 2, 0, 2,
 2550000, 100000, 0, 2650000, 'VND', NULL, NULL,
 'checkout', 'paid', NULL, NULL,
 '2026-08-05 09:30:00', '2026-08-05 10:00:00', '2026-08-10 14:20:00', '2026-08-13 11:45:00', NULL),
-- 6. Hanoi Vintage Loft — đã hủy (cancelled)
(6, 'YH202608120010', 3, 2, 'Lê Hoàng Nam', 'hoang.nam@example.com', '0923456789',
 '2026-08-12', '2026-08-14', 2, 2, 0, 2,
 2100000, 100000, 0, 2200000, 'VND', NULL, NULL,
 'cancelled', 'unpaid', NULL, 'Khách thay đổi kế hoạch chuyến đi',
 '2026-08-09 16:00:00', NULL, NULL, NULL, '2026-08-10 09:00:00'),
-- 7. West Lake Sunset Studio — đang lưu trú (checkin)
(7, 'YH202608140004', 2, 7, 'Trần Thu Hà', 'thu.ha@example.com', '0912345678',
 '2026-08-14', '2026-08-17', 3, 2, 0, 2,
 4500000, 150000, 0, 4650000, 'VND', NULL, NULL,
 'checkin', 'paid', NULL, NULL,
 '2026-08-10 10:00:00', '2026-08-10 10:30:00', '2026-08-14 14:00:00', NULL, NULL),
-- 8. Hanoi Vintage Loft — đang lưu trú (checkin)
(8, 'YH202608150005', 4, 2, 'Phạm Quỳnh Trang', 'quynh.trang@example.com', '0934567890',
 '2026-08-15', '2026-08-18', 3, 2, 0, 2,
 3150000, 100000, 0, 3250000, 'VND', NULL, NULL,
 'checkin', 'partial', 'Xin thêm 2 gối.', NULL,
 '2026-08-11 14:00:00', '2026-08-11 14:30:00', '2026-08-15 15:00:00', NULL, NULL),
-- 9. Cau Giay Business Apartment — đã xác nhận, dùng mã HANOI150
(9, 'YH202608180006', 6, 14, 'Đặng Thị Mai', 'thi.mai@example.com', '0956789012',
 '2026-08-18', '2026-08-21', 3, 1, 0, 1,
 3600000, 100000, 150000, 3550000, 'VND', 2, 'HANOI150',
 'confirmed', 'unpaid', 'Nhận phòng muộn khoảng 21h.', NULL,
 '2026-08-14 09:00:00', '2026-08-15 10:00:00', NULL, NULL, NULL),
-- 10. Cau Giay Modern Studio — đã xác nhận — 1 đêm cuối tuần (rule 3)
(10, 'YH202608200007', 7, 13, 'Bùi Quang Minh', 'quang.minh@example.com', '0967890123',
 '2026-08-20', '2026-08-22', 2, 1, 0, 1,
 1500000, 80000, 0, 1580000, 'VND', NULL, NULL,
 'confirmed', 'unpaid', NULL, NULL,
 '2026-08-15 08:00:00', '2026-08-16 09:00:00', NULL, NULL, NULL),
-- 11. Hoan Kiem Couple Studio — đã xác nhận, dùng mã YANGNEW10 — 1 đêm cuối tuần (rule 4)
(11, 'YH202608220008', 8, 3, 'Hoàng Thu Phương', 'thu.phuong@example.com', '0978901234',
 '2026-08-22', '2026-08-25', 3, 2, 0, 2,
 2950000, 100000, 295000, 2755000, 'VND', 1, 'YANGNEW10',
 'confirmed', 'unpaid', 'Kỷ niệm ngày cưới, xin trang trí nhẹ.', NULL,
 '2026-08-16 10:00:00', '2026-08-16 11:00:00', NULL, NULL, NULL),
-- 12. Ba Dinh Family Apartment — chờ xác nhận (pending)
(12, 'YH202609010009', 1, 6, 'Nguyễn Minh Anh', 'minh.anh@example.com', '0901234567',
 '2026-09-01', '2026-09-04', 3, 2, 2, 4,
 6600000, 200000, 0, 6800000, 'VND', NULL, NULL,
 'pending', 'unpaid', 'Gia đình có 2 trẻ nhỏ.', NULL,
 '2026-08-16 10:30:00', NULL, NULL, NULL, NULL);

-- ═════════════════════════════════════════════════════════════════════════
-- 15. BOOKING_NIGHTS (33) — price snapshot từng đêm, khớp subtotal
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO booking_nights (id, booking_id, stay_date, nightly_rate, rate_rule_id, created_at) VALUES
-- Booking 1 (unit 10, base 650000)
(1,  1, '2026-08-01', 650000, NULL, '2026-07-25 10:00:00'),
(2,  1, '2026-08-02', 650000, NULL, '2026-07-25 10:00:00'),
(3,  1, '2026-08-03', 650000, NULL, '2026-07-25 10:00:00'),
-- Booking 2 (unit 5, base 1100000)
(4,  2, '2026-08-03', 1100000, NULL, '2026-07-28 09:00:00'),
(5,  2, '2026-08-04', 1100000, NULL, '2026-07-28 09:00:00'),
-- Booking 3 (unit 4, base 1200000; đêm 07/08 Thứ 6 = rule 1: 1500000)
(6,  3, '2026-08-05', 1200000, NULL, '2026-07-30 15:00:00'),
(7,  3, '2026-08-06', 1200000, NULL, '2026-07-30 15:00:00'),
(8,  3, '2026-08-07', 1500000, 1,   '2026-07-30 15:00:00'),
-- Booking 4 (unit 8, base 1800000; 07/08 = rule 5, 08/08 = rule 6: 1950000)
(9,  4, '2026-08-06', 1800000, NULL, '2026-07-29 11:00:00'),
(10, 4, '2026-08-07', 1950000, 5,   '2026-07-29 11:00:00'),
(11, 4, '2026-08-08', 1950000, 6,   '2026-07-29 11:00:00'),
-- Booking 5 (unit 1, base 850000)
(12, 5, '2026-08-10', 850000, NULL, '2026-08-05 09:30:00'),
(13, 5, '2026-08-11', 850000, NULL, '2026-08-05 09:30:00'),
(14, 5, '2026-08-12', 850000, NULL, '2026-08-05 09:30:00'),
-- Booking 6 (unit 2, base 1050000 — cancelled)
(15, 6, '2026-08-12', 1050000, NULL, '2026-08-09 16:00:00'),
(16, 6, '2026-08-13', 1050000, NULL, '2026-08-09 16:00:00'),
-- Booking 7 (unit 7, base 1500000)
(17, 7, '2026-08-14', 1500000, NULL, '2026-08-10 10:00:00'),
(18, 7, '2026-08-15', 1500000, NULL, '2026-08-10 10:00:00'),
(19, 7, '2026-08-16', 1500000, NULL, '2026-08-10 10:00:00'),
-- Booking 8 (unit 2, base 1050000)
(20, 8, '2026-08-15', 1050000, NULL, '2026-08-11 14:00:00'),
(21, 8, '2026-08-16', 1050000, NULL, '2026-08-11 14:00:00'),
(22, 8, '2026-08-17', 1050000, NULL, '2026-08-11 14:00:00'),
-- Booking 9 (unit 14, base 1200000)
(23, 9, '2026-08-18', 1200000, NULL, '2026-08-14 09:00:00'),
(24, 9, '2026-08-19', 1200000, NULL, '2026-08-14 09:00:00'),
(25, 9, '2026-08-20', 1200000, NULL, '2026-08-14 09:00:00'),
-- Booking 10 (unit 13, base 700000; đêm 21/08 Thứ 6 = rule 3: 800000)
(26, 10, '2026-08-20', 700000, NULL, '2026-08-15 08:00:00'),
(27, 10, '2026-08-21', 800000, 3,   '2026-08-15 08:00:00'),
-- Booking 11 (unit 3, base 950000; đêm 22/08 Thứ 7 = rule 4: 1050000)
(28, 11, '2026-08-22', 1050000, 4,   '2026-08-16 10:00:00'),
(29, 11, '2026-08-23', 950000, NULL, '2026-08-16 10:00:00'),
(30, 11, '2026-08-24', 950000, NULL, '2026-08-16 10:00:00'),
-- Booking 12 (unit 6, base 2200000)
(31, 12, '2026-09-01', 2200000, NULL, '2026-08-16 10:30:00'),
(32, 12, '2026-09-02', 2200000, NULL, '2026-08-16 10:30:00'),
(33, 12, '2026-09-03', 2200000, NULL, '2026-08-16 10:30:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 16. BOOKING_PRICE_ITEMS — dòng tiền hiển thị hóa đơn
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO booking_price_items (id, booking_id, item_type, label, amount, display_order, created_at) VALUES
-- Booking 1
(1,  1,  'accommodation', '3 đêm × 650.000₫',           1950000, 1, '2026-07-25 10:00:00'),
(2,  1,  'cleaning_fee',  'Phí vệ sinh',                 80000, 2, '2026-07-25 10:00:00'),
-- Booking 2
(3,  2,  'accommodation', '2 đêm × 1.100.000₫',         2200000, 1, '2026-07-28 09:00:00'),
(4,  2,  'cleaning_fee',  'Phí vệ sinh',                100000, 2, '2026-07-28 09:00:00'),
-- Booking 3
(5,  3,  'accommodation', '3 đêm (gồm 1 đêm cuối tuần)', 3900000, 1, '2026-07-30 15:00:00'),
(6,  3,  'cleaning_fee',  'Phí vệ sinh',                120000, 2, '2026-07-30 15:00:00'),
-- Booking 4
(7,  4,  'accommodation', '3 đêm (gồm 2 đêm cuối tuần)', 5700000, 1, '2026-07-29 11:00:00'),
(8,  4,  'cleaning_fee',  'Phí vệ sinh',                150000, 2, '2026-07-29 11:00:00'),
-- Booking 5
(9,  5,  'accommodation', '3 đêm × 850.000₫',           2550000, 1, '2026-08-05 09:30:00'),
(10, 5,  'cleaning_fee',  'Phí vệ sinh',                100000, 2, '2026-08-05 09:30:00'),
-- Booking 6
(11, 6,  'accommodation', '2 đêm × 1.050.000₫',         2100000, 1, '2026-08-09 16:00:00'),
(12, 6,  'cleaning_fee',  'Phí vệ sinh',                100000, 2, '2026-08-09 16:00:00'),
-- Booking 7
(13, 7,  'accommodation', '3 đêm × 1.500.000₫',         4500000, 1, '2026-08-10 10:00:00'),
(14, 7,  'cleaning_fee',  'Phí vệ sinh',                150000, 2, '2026-08-10 10:00:00'),
-- Booking 8
(15, 8,  'accommodation', '3 đêm × 1.050.000₫',         3150000, 1, '2026-08-11 14:00:00'),
(16, 8,  'cleaning_fee',  'Phí vệ sinh',                100000, 2, '2026-08-11 14:00:00'),
-- Booking 9
(17, 9,  'accommodation', '3 đêm × 1.200.000₫',         3600000, 1, '2026-08-14 09:00:00'),
(18, 9,  'cleaning_fee',  'Phí vệ sinh',                100000, 2, '2026-08-14 09:00:00'),
(19, 9,  'discount',      'Mã HANOI150 (−150.000₫)',    -150000, 3, '2026-08-14 09:00:00'),
-- Booking 10
(20, 10, 'accommodation', '2 đêm (gồm 1 đêm cuối tuần)', 1500000, 1, '2026-08-15 08:00:00'),
(21, 10, 'cleaning_fee',  'Phí vệ sinh',                 80000, 2, '2026-08-15 08:00:00'),
-- Booking 11
(22, 11, 'accommodation', '3 đêm (gồm 1 đêm cuối tuần)', 2950000, 1, '2026-08-16 10:00:00'),
(23, 11, 'cleaning_fee',  'Phí vệ sinh',                100000, 2, '2026-08-16 10:00:00'),
(24, 11, 'discount',      'Mã YANGNEW10 (−10%)',        -295000, 3, '2026-08-16 10:00:00'),
-- Booking 12
(25, 12, 'accommodation', '3 đêm × 2.200.000₫',         6600000, 1, '2026-08-16 10:30:00'),
(26, 12, 'cleaning_fee',  'Phí vệ sinh',                200000, 2, '2026-08-16 10:30:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 17. PROMOTION_USAGES (2) — khớp với discount_amount của booking
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO promotion_usages (id, promotion_id, customer_id, booking_id, discount_amount, used_at) VALUES
(1, 2, 6, 9,  150000, '2026-08-14 09:05:00'),
(2, 1, 8, 11, 295000, '2026-08-16 10:05:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 18. FAVORITES (5)
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO favorites (id, customer_id, unit_id, created_at) VALUES
(1, 1, 4,  '2026-07-10 09:00:00'),
(2, 2, 7,  '2026-07-15 10:00:00'),
(3, 3, 1,  '2026-07-20 11:00:00'),
(4, 5, 8,  '2026-07-25 14:00:00'),
(5, 8, 3,  '2026-08-01 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 19. REVIEWS (5) — CHỈ gắn với booking status = checkout
--     Rating thực tế (3-5), không tất cả 5.0
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO reviews
(id, booking_id, customer_id, unit_id,
 overall_rating, cleanliness_rating, location_rating, amenities_rating, accuracy_rating, communication_rating, value_rating,
 title, comment, status, created_at, updated_at, approved_at, approved_by_admin_id) VALUES
(1, 5, 3, 1,
 4, 4, 5, 4, 4, 5, 4,
 'Studio đẹp giữa lòng phố cổ',
 'Studio ấm cúng, ban công nhìn ra phố cổ rất thích. Vị trí thuận tiện đi bộ ra Hồ Gươm. Giá hơi cao so với diện tích nhưng đáng trải nghiệm.',
 'approved', '2026-08-14 09:00:00', '2026-08-14 09:00:00', '2026-08-14 15:00:00', 1),
(2, 3, 1, 4,
 5, 5, 4, 5, 5, 5, 5,
 'View hồ Trúc Bạch tuyệt đẹp',
 'Sáng mở cửa ra là thấy hồ, không khí trong lành. Phòng sạch sẽ, đầy đủ tiện nghi. Sẽ quay lại!',
 'approved', '2026-08-09 09:00:00', '2026-08-09 09:00:00', '2026-08-10 10:00:00', 1),
(3, 1, 5, 10,
 3, 3, 4, 3, 3, 4, 4,
 'Giá tốt nhưng phòng hơi nhỏ',
 'Giá hợp lý, vị trí gần đường tàu. Phòng hơi nhỏ và cách âm chưa tốt, nhưng chủ nhà hỗ trợ nhiệt tình.',
 'approved', '2026-08-05 09:00:00', '2026-08-05 09:00:00', '2026-08-06 10:00:00', 1),
(4, 2, 5, 5,
 4, 4, 4, 4, 4, 4, 5,
 'Ban công nhìn ra hồ rất thư giãn',
 'Không gian ấm cúng, ban công nhỏ ngồi uống cà phê sáng rất thích. Giá hợp lý cho vị trí gần hồ.',
 'pending', '2026-08-06 10:00:00', '2026-08-06 10:00:00', NULL, NULL),
(5, 4, 4, 8,
 5, 5, 4, 5, 5, 5, 5,
 'Không gian làm việc lý tưởng cho workcation',
 'Bàn làm việc thoải mái, wifi mạnh, view hồ Tây giúp làm việc hiệu quả. Máy chiếu tiện lợi xem phim buổi tối.',
 'approved', '2026-08-10 09:00:00', '2026-08-10 09:00:00', '2026-08-11 10:00:00', 2);

-- ═════════════════════════════════════════════════════════════════════════
-- 20. NEARBY_PLACES (11) — khoảng cách là ước lượng DEMO
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO nearby_places (id, property_id, name, category, short_description, latitude, longitude, distance_meters, walking_minutes, image_url, featured, created_at) VALUES
(1,  1, 'Hồ Hoàn Kiếm',            'attraction',  'Điểm đến biểu tượng của Hà Nội, thích hợp dạo bộ buổi sáng.',    21.0285110, 105.8521730, 400,  5,  'assets/images/areas/hoan-kiem-lake.jpg', 1, '2026-05-01 10:00:00'),
(2,  1, 'Ẩm thực phố Hàng Bạc',    'food',        'Quán ăn vặt và đặc sản Hà Nội ngay trong khu phố cổ.',           21.0340000, 105.8510000, 120,  2,  'assets/images/experiences/hanoi-food/vietnamese-meal.jpg', 1, '2026-05-01 10:00:00'),
(3,  1, 'Chợ đêm phố cổ',          'nightlife',   'Chợ đêm cuối tuần đông vui, nhiều quà lưu niệm.',                  21.0350000, 105.8490000, 350,  5,  'assets/images/areas/old-quarter.jpg', 0, '2026-05-01 10:00:00'),
(4,  2, 'Hồ Trúc Bạch',            'attraction',  'Hồ yên tĩnh, không khí trong lành, gần Hồ Tây.',                    21.0460000, 105.8400000, 200,  3,  'assets/images/areas/west-lake.jpg', 1, '2026-05-03 10:00:00'),
(5,  2, 'Phở gánh Quán Thánh',     'food',        'Quán phở nổi tiếng, đông khách từ sáng sớm.',                      21.0400000, 105.8450000, 800, 10,  'assets/images/experiences/hanoi-food/pho.jpg', 0, '2026-05-03 10:00:00'),
(6,  3, 'Đường dạo quanh Hồ Tây',  'attraction',  'Ngắm hoàng hôn xuống hồ, đường dạo thoáng mát.',                    21.0680000, 105.8170000, 300,  4,  'assets/images/areas/west-lake.jpg', 1, '2026-05-05 10:00:00'),
(7,  3, 'Quán cà phê ngắm hoàng hôn','coffee',    'Cà phê view hồ Tây, điểm hẹn của giới trẻ.',                        21.0720000, 105.8140000, 150,  2,  'assets/images/experiences/hanoi-food/egg-coffee.jpg', 0, '2026-05-05 10:00:00'),
(8,  4, 'Văn Miếu – Quốc Tử Giám', 'attraction',  'Di tích lịch sử nghìn năm, kiến trúc cổ kính.',                     21.0289000, 105.8355000, 1200, 15, NULL, 1, '2026-05-08 10:00:00'),
(9,  4, 'Đường tàu Hà Nội',        'photography', 'Đoạn đường ray len lỏi giữa phố, điểm chụp ảnh nổi tiếng.',           21.0230000, 105.8470000, 900, 12, NULL, 0, '2026-05-08 10:00:00'),
(10, 5, 'Hồ Nghĩa Tân',            'attraction',  'Hồ điều hòa yên bình, thích hợp đi bộ buổi tối.',                    21.0330000, 105.7900000, 500,  7,  NULL, 0, '2026-05-10 10:00:00'),
(11, 5, 'Xóm cà phê Cầu Giấy',     'coffee',      'Nhiều quán cà phê làm việc, mở cửa tới khuya.',                      21.0330000, 105.7970000, 250,  3,  'assets/images/experiences/hanoi-food/egg-coffee.jpg', 0, '2026-05-10 10:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 21. NOTIFICATIONS (6)
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO notifications (id, customer_id, admin_id, type, title, message, entity_type, entity_id, is_read, created_at, read_at) VALUES
(1, 6, NULL, 'booking_created',     'Đơn đặt phòng đã được tạo',       'Đơn YH202608180006 đã được ghi nhận, đang chờ xác nhận.', 'booking', 9,  1, '2026-08-14 09:00:00', '2026-08-14 20:00:00'),
(2, 6, NULL, 'booking_confirmed',   'Đặt phòng đã được xác nhận',      'Đơn YH202608180006 của bạn đã được xác nhận.',            'booking', 9,  0, '2026-08-15 10:00:00', NULL),
(3, 2, NULL, 'checkin_reminder',    'Sắp đến ngày nhận phòng',         'Bạn sẽ nhận phòng YangHomestay Tây Hồ vào 14/08.',        'booking', 7,  1, '2026-08-13 08:00:00', '2026-08-13 21:30:00'),
(4, 1, NULL, 'review_invitation',   'Chia sẻ trải nghiệm của bạn',     'Bạn vừa hoàn tất kỳ nghỉ tại Truc Bach Lake View Studio.', 'booking', 3, 0, '2026-08-09 09:00:00', NULL),
(5, NULL, 1,  'new_booking_admin',  'Có đặt phòng mới',                'Booking YH202609010009 vừa được tạo từ website.',          'booking', 12, 0, '2026-08-16 10:30:00', NULL),
(6, 8, NULL, 'booking_confirmed',   'Đặt phòng đã được xác nhận',      'Đơn YH202608220008 của bạn đã được xác nhận.',            'booking', 11, 0, '2026-08-16 11:00:00', NULL);

-- ═════════════════════════════════════════════════════════════════════════
-- 22. CONTACT_MESSAGES (3)
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO contact_messages (id, full_name, email, phone, subject, message, status, created_at, updated_at) VALUES
(1, 'Nguyễn Văn An',   'an.nguyen@example.com',   '0901000001', 'Hỏi về giờ nhận phòng',
 'Xin chào, tôi muốn hỏi có thể nhận phòng sớm hơn giờ quy định không? Nếu được thì có tính phí không?',
 'replied', '2026-08-10 09:00:00', '2026-08-10 14:00:00'),
(2, 'Trần Thị Bích',   'bich.tran@example.com',   '0901000002', 'Giá thuê dài hạn',
 'Mình muốn thuê dài hạn 1 tháng ở khu Cầu Giấy, có ưu đãi gì cho khách ở dài ngày không?',
 'new',     '2026-08-14 15:30:00', '2026-08-14 15:30:00'),
(3, 'Lê Văn Cường',    'cuong.le@example.com',    '0901000003', 'Góp ý về website',
 'Website rất dễ dùng và đẹp. Mong thêm bản đồ khu vực và ảnh thật của từng căn hộ.',
 'read',     '2026-08-12 10:00:00', '2026-08-12 10:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 23. LOGIN_LOGS (7) — KHÔNG log password
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO login_logs (id, actor_type, actor_id, email_or_username, success, ip_address, user_agent, created_at) VALUES
(1, 'admin',    1,    'admin',                   1, '127.0.0.1',     'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-08-16 08:30:00'),
(2, 'admin',    2,    'manager',                 1, '127.0.0.1',     'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-08-15 09:00:00'),
(3, 'admin',    NULL, 'admin',                   0, '127.0.0.1',     'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-08-14 22:10:00'),
(4, 'customer', 1,    'minh.anh@example.com',    1, '192.168.1.10',  'Mozilla/5.0 (Linux; Android 13) Mobile Safari/537.36',          '2026-08-16 09:00:00'),
(5, 'customer', 3,    'hoang.nam@example.com',   1, '192.168.1.20',  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile Safari/604.1',  '2026-08-10 10:00:00'),
(6, 'customer', NULL, 'unknown@example.com',     0, '203.0.113.5',   'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2026-08-12 20:00:00'),
(7, 'customer', 5,    'duc.huy@example.com',     1, '192.168.1.30',  'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Safari/605.1.15',  '2026-08-13 08:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- 24. AUDIT_LOGS (5) — nhật ký thao tác admin
-- ═════════════════════════════════════════════════════════════════════════
INSERT INTO audit_logs (id, admin_id, action, entity_type, entity_id, old_value_json, new_value_json, ip_address, created_at) VALUES
(1, 1, 'UPDATE_UNIT_PRICE',      'units',    4,  '{"base_price":1100000}',               '{"base_price":1200000}',               '127.0.0.1', '2026-07-15 10:00:00'),
(2, 1, 'BLOCK_DATES',            'units',    11, '{}',                                    '{"start_date":"2026-08-20","end_date":"2026-08-23","reason":"maintenance"}', '127.0.0.1', '2026-08-11 10:00:00'),
(3, 2, 'CHANGE_BOOKING_STATUS',  'bookings', 7,  '{"status":"confirmed"}',               '{"status":"checkin"}',                 '127.0.0.1', '2026-08-14 14:00:00'),
(4, 1, 'APPROVE_REVIEW',         'reviews',  1,  '{"status":"pending"}',                 '{"status":"approved"}',                '127.0.0.1', '2026-08-14 15:00:00'),
(5, 3, 'UPDATE_PROPERTY',        'properties',3, '{"featured":0}',                       '{"featured":1}',                       '127.0.0.1', '2026-08-05 09:00:00');

-- ═════════════════════════════════════════════════════════════════════════
-- KẾT THÚC SEED — dữ liệu demo coherent, không orphan.
-- ═════════════════════════════════════════════════════════════════════════
