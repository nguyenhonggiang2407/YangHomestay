-- YangHomestay — 013_final_consistency_hardening.sql
-- Mục tiêu: sửa dữ liệu demo còn lệch capacity/claim hình ảnh sau migration 012.
-- An toàn cho production: không TRUNCATE, không xóa booking/user/payment history.
BEGIN;

-- 1) Unit 13: bỏ ảnh phụ từng trùng chính xác với asset của unit khác.
--    Ảnh bedroom còn lại đã thể hiện quầy bar/mặt bàn; mô tả được chỉnh đúng chức năng thay vì ghép thêm ảnh stock.
DELETE FROM public.unit_images
WHERE unit_id = 13
  AND image_url IN (
    'assets/images/homestays/cau-giay-modern-studio/workspace.jpg',
    'assets/images/homestays/cau-giay-modern-studio/kitchen-bedroom.jpg'
  );

UPDATE public.units
SET description = 'Studio 26m² nội thất hiện đại, có thang máy; quầy bar nhỏ có thể dùng làm bàn làm việc. Phù hợp khách công tác ngắn ngày.'
WHERE id = 13;

-- 2) West Lake Family Apartment: bỏ claim direct lake-view khi gallery chỉ đủ
--    chứng minh vị trí gần Hồ Tây. Ảnh cuối được ghi rõ là ảnh khu vực.
UPDATE public.unit_images
SET image_url = 'assets/images/homestays/experiences/west-lake-view.jpg',
    caption = 'West Lake Family Apartment — Hồ Tây trong khu vực lưu trú',
    image_category = 'other'
WHERE unit_id = 9
  AND image_url = 'assets/images/homestays/west-lake-sunset-studio/lake-view.jpg';

DELETE FROM public.unit_amenities
WHERE unit_id = 9 AND amenity_id = 11; -- lake_view

DELETE FROM public.unit_experience_tags
WHERE (unit_id = 9 AND tag_id = 16)
   OR (unit_id = 5 AND tag_id = 16); -- no unsupported direct lake-view tag

UPDATE public.units
SET short_description = 'Phòng có ban công thoáng gần hồ Trúc Bạch, không gian ấm cúng giá hợp lý.',
    description = 'Phòng nhỏ gọn nhưng đủ đầy với ban công riêng, giường đôi và khu vực trà cà phê; phù hợp khách muốn ở gần hồ Trúc Bạch nhưng ưu tiên mức giá vừa phải.'
WHERE id = 5;

UPDATE public.units
SET short_description = 'Căn hộ gia đình gần Hồ Tây, 2 phòng ngủ cho tối đa 4 khách.',
    description = 'Căn hộ 85m² với 2 phòng ngủ, 3 giường (1 giường đôi + 2 giường đơn), phòng khách rộng, bếp đầy đủ và ban công dài. Phù hợp gia đình tối đa 4 người muốn ở khu Tây Hồ nhưng không cần đánh đổi không gian sinh hoạt.'
WHERE id = 9;

-- 3) Booking demo #8 từng có 3 khách trong unit #2 chỉ có sức chứa 2.
--    Giữ lịch/giá/trạng thái, chỉ sửa guest snapshot cho nhất quán capacity.
UPDATE public.bookings
SET adults = 2,
    children = 0,
    total_guests = 2,
    special_requests = 'Xin thêm 2 gối.'
WHERE id = 8
  AND unit_id = 2;

COMMIT;
