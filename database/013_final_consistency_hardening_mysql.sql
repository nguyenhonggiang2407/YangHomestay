-- YangHomestay — MySQL equivalent of Supabase migration 013.
START TRANSACTION;

-- 1) Unit 13: bỏ ảnh phụ từng trùng chính xác với asset của unit khác.
DELETE FROM unit_images
WHERE unit_id = 13
  AND image_url IN (
    'assets/images/homestays/cau-giay-modern-studio/workspace.jpg',
    'assets/images/homestays/cau-giay-modern-studio/kitchen-bedroom.jpg'
  );

UPDATE units
SET description = 'Studio 26m² nội thất hiện đại, có thang máy; quầy bar nhỏ có thể dùng làm bàn làm việc. Phù hợp khách công tác ngắn ngày.'
WHERE id = 13;

UPDATE unit_images
SET image_url = 'assets/images/homestays/experiences/west-lake-view.jpg',
    caption = 'West Lake Family Apartment — Hồ Tây trong khu vực lưu trú',
    image_category = 'other'
WHERE unit_id = 9
  AND image_url = 'assets/images/homestays/west-lake-sunset-studio/lake-view.jpg';

DELETE FROM unit_amenities WHERE unit_id = 9 AND amenity_id = 11;
DELETE FROM unit_experience_tags
WHERE (unit_id = 9 AND tag_id = 16)
   OR (unit_id = 5 AND tag_id = 16);

UPDATE units
SET short_description = 'Phòng có ban công thoáng gần hồ Trúc Bạch, không gian ấm cúng giá hợp lý.',
    description = 'Phòng nhỏ gọn nhưng đủ đầy với ban công riêng, giường đôi và khu vực trà cà phê; phù hợp khách muốn ở gần hồ Trúc Bạch nhưng ưu tiên mức giá vừa phải.'
WHERE id = 5;

UPDATE units
SET short_description = 'Căn hộ gia đình gần Hồ Tây, 2 phòng ngủ cho tối đa 4 khách.',
    description = 'Căn hộ 85m² với 2 phòng ngủ, 3 giường (1 giường đôi + 2 giường đơn), phòng khách rộng, bếp đầy đủ và ban công dài. Phù hợp gia đình tối đa 4 người muốn ở khu Tây Hồ nhưng không cần đánh đổi không gian sinh hoạt.'
WHERE id = 9;

UPDATE bookings
SET adults = 2,
    children = 0,
    total_guests = 2,
    special_requests = 'Xin thêm 2 gối.'
WHERE id = 8
  AND unit_id = 2;

COMMIT;
