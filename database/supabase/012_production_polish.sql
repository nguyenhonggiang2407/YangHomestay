-- 012_production_polish.sql
-- Production normalization for an existing Supabase database already migrated through 011.
-- Idempotent for the canonical 5 properties / 16 units bundled with YangHomestay.
BEGIN;

-- Repair canonical unit code accidentally corrupted in an earlier seed.
UPDATE public.units SET code = 'YH-CG-04' WHERE id = 16;
UPDATE public.units
SET description = 'Studio 26m² nội thất hiện đại, có thang máy; quầy bar nhỏ có thể dùng làm bàn làm việc. Phù hợp khách công tác ngắn ngày.'
WHERE id = 13;


UPDATE public.properties SET address = 'Khu vực Phố Cổ, Hoàn Kiếm, Hà Nội', updated_at = now() WHERE id = 1;
UPDATE public.properties SET address = 'Khu vực Trúc Bạch, Ba Đình, Hà Nội', updated_at = now() WHERE id = 2;
UPDATE public.properties SET address = 'Khu vực Hồ Tây, Tây Hồ, Hà Nội', updated_at = now() WHERE id = 3;
UPDATE public.properties SET address = 'Khu vực Đống Đa, Hà Nội', updated_at = now() WHERE id = 4;
UPDATE public.properties SET address = 'Khu vực Cầu Giấy, Hà Nội', updated_at = now() WHERE id = 5;

UPDATE public.units SET max_guests = 2, beds = 1, short_description = 'Gác xép cổ điển cho 2 khách, nội thất gỗ và trần cao.', description = 'Loft 2 tầng nhỏ với trần cao, gạch cổ và nội thất gỗ. Gác xép có 1 giường đôi cho tối đa 2 khách; tầng dưới là bếp nhỏ và bàn làm việc.', updated_at = now() WHERE id = 2;
UPDATE public.units SET max_guests = 4, beds = 3, short_description = 'Căn hộ 2 phòng ngủ rộng rãi cho gia đình tối đa 4 khách.', description = 'Căn hộ 70m² với 2 phòng ngủ, 3 giường (1 giường đôi + 2 giường đơn), bếp đầy đủ dụng cụ, máy giặt và ban công. Phù hợp gia đình tối đa 4 người ở dài ngày.', updated_at = now() WHERE id = 6;
UPDATE public.units SET max_guests = 2, beds = 1, short_description = 'Loft view hồ cho 1-2 khách, có góc làm việc riêng và wifi mạnh.', description = 'Loft 40m² cho tối đa 2 khách với 1 giường, bàn làm việc riêng, wifi tốc độ cao, máy chiếu và view hồ Tây thư giãn.', updated_at = now() WHERE id = 8;
UPDATE public.units SET max_guests = 4, beds = 3, short_description = 'Căn hộ gia đình view hồ Tây, 2 phòng ngủ cho tối đa 4 khách.', description = 'Căn hộ 85m² với 2 phòng ngủ, 3 giường (1 giường đôi + 2 giường đơn), phòng khách rộng, bếp đầy đủ và ban công dài ngắm hồ. Phù hợp gia đình tối đa 4 người.', updated_at = now() WHERE id = 9;
UPDATE public.units SET max_guests = 2, beds = 1, short_description = 'Loft trẻ trung cho tối đa 2 khách, có gác ngủ và máy chiếu.', description = 'Loft hiện đại với 1 giường ở gác ngủ, máy chiếu xem phim và góc làm việc. Phù hợp khách đi một mình hoặc hai người.', updated_at = now() WHERE id = 11;
UPDATE public.units SET max_guests = 3, beds = 2, short_description = 'Căn hộ có ban công xanh cho tối đa 3 khách, có sofa bed.', description = 'Căn hộ 50m² với 1 giường chính và 1 sofa bed, ban công trồng cây, bếp đầy đủ và máy giặt. Phù hợp gia đình nhỏ tối đa 3 khách.', updated_at = now() WHERE id = 12;
UPDATE public.units SET max_guests = 2, beds = 1, short_description = 'Căn hộ 1 phòng ngủ cho 1-2 khách công tác, gần khu văn phòng.', description = 'Căn hộ 42m² cho tối đa 2 khách với 1 giường, phòng ngủ riêng, bàn làm việc rộng, bếp và máy giặt. Gần nhiều tòa văn phòng tại Cầu Giấy.', updated_at = now() WHERE id = 14;
UPDATE public.units SET max_guests = 4, beds = 3, short_description = 'Suite 2 phòng ngủ cho gia đình, đầy đủ tiện nghi.', description = 'Suite 60m² với 2 phòng ngủ, tổng 3 giường, 2 nhà tắm, bếp và ban công. Phù hợp gia đình tối đa 4 người hoặc nhóm bạn.', updated_at = now() WHERE id = 16;

-- Remove user-visible sample marker from seeded rate-rule labels.
UPDATE public.rate_rules SET name = replace(name, ' (demo)', '') WHERE name LIKE '% (demo)%';

-- Normalize property gallery to the same local assets as the canonical seed.
DELETE FROM public.property_images WHERE property_id BETWEEN 1 AND 5;
INSERT INTO public.property_images (property_id, storage_path, image_url, caption, image_category, display_order, is_cover, created_at) VALUES
(1, NULL, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Không gian khu Phố Cổ', 'exterior', 0, TRUE, '2026-05-15 10:00:00+07'),
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/cover.jpg', 'Không gian lưu trú tại Phố Cổ', 'common_area', 1, FALSE, '2026-05-15 10:00:00+07'),
(1, NULL, 'assets/images/homestays/hanoi-vintage-loft/cover.jpg', 'Nội thất chọn lọc tại Phố Cổ', 'common_area', 2, FALSE, '2026-05-15 10:00:00+07'),
(2, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/window-view.jpg', 'Góc nhìn thoáng tại Trúc Bạch', 'view', 0, TRUE, '2026-05-15 10:00:00+07'),
(2, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/cover.jpg', 'Không gian lưu trú bên hồ Trúc Bạch', 'common_area', 1, FALSE, '2026-05-15 10:00:00+07'),
(2, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Ban công xanh tại Trúc Bạch', 'balcony', 2, FALSE, '2026-05-15 10:00:00+07'),
(3, NULL, 'assets/images/homestays/west-lake-sunset-studio/lake-view.jpg', 'Góc nhìn Hồ Tây', 'view', 0, TRUE, '2026-05-15 10:00:00+07'),
(3, NULL, 'assets/images/homestays/west-lake-sunset-studio/cover.jpg', 'Không gian lưu trú tại Hồ Tây', 'common_area', 1, FALSE, '2026-05-15 10:00:00+07'),
(3, NULL, 'assets/images/homestays/west-lake-workcation-loft/cover.jpg', 'Không gian workcation tại Hồ Tây', 'common_area', 2, FALSE, '2026-05-15 10:00:00+07'),
(4, NULL, 'assets/images/homestays/dong-da-garden-apartment/cover.jpg', 'Không gian lưu trú tại Đống Đa', 'common_area', 0, TRUE, '2026-05-15 10:00:00+07'),
(4, NULL, 'assets/images/homestays/dong-da-cozy-studio/cover.jpg', 'Studio ấm cúng tại Đống Đa', 'common_area', 1, FALSE, '2026-05-15 10:00:00+07'),
(4, NULL, 'assets/images/homestays/dong-da-urban-loft/cover.jpg', 'Loft đô thị tại Đống Đa', 'common_area', 2, FALSE, '2026-05-15 10:00:00+07'),
(5, NULL, 'assets/images/homestays/cau-giay-family-suite/cover.jpg', 'Không gian căn hộ tại Cầu Giấy', 'common_area', 0, TRUE, '2026-05-15 10:00:00+07'),
(5, NULL, 'assets/images/homestays/cau-giay-modern-studio/cover.jpg', 'Studio hiện đại tại Cầu Giấy', 'common_area', 1, FALSE, '2026-05-15 10:00:00+07'),
(5, NULL, 'assets/images/homestays/cau-giay-business-apartment/cover.jpg', 'Căn hộ công tác tại Cầu Giấy', 'common_area', 2, FALSE, '2026-05-15 10:00:00+07');

-- Normalize the 16 bundled unit galleries. This intentionally replaces only units 1..16.
DELETE FROM public.unit_images WHERE unit_id BETWEEN 1 AND 16;
INSERT INTO public.unit_images (unit_id, storage_path, image_url, caption, image_category, display_order, is_cover, created_at) VALUES
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/cover.jpg', 'Old Quarter Balcony Studio — Không gian sinh hoạt vintage', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/bedroom.jpg', 'Old Quarter Balcony Studio — Giường đôi', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/balcony.jpg', 'Old Quarter Balcony Studio — Ban công riêng', 'balcony', 2, FALSE, '2026-08-17 13:01:00+07'),
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/bathroom.jpg', 'Old Quarter Balcony Studio — Phòng tắm phong cách vintage', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(2, NULL, 'assets/images/homestays/hanoi-vintage-loft/cover.jpg', 'Hanoi Vintage Loft — Không gian loft', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(2, NULL, 'assets/images/homestays/hanoi-vintage-loft/bedroom.jpg', 'Hanoi Vintage Loft — Giường đôi trên gác', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(2, NULL, 'assets/images/homestays/hanoi-vintage-loft/bathroom.jpg', 'Hanoi Vintage Loft — Phòng tắm phong cách vintage', 'bathroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(3, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/cover.jpg', 'Hoan Kiem Couple Studio — Phòng ngủ chính', 'bedroom', 0, TRUE, '2026-08-17 13:01:00+07'),
(3, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/bedroom-2.jpg', 'Hoan Kiem Couple Studio — Góc nghỉ sáng tự nhiên', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(3, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/bathroom.jpg', 'Hoan Kiem Couple Studio — Bồn tắm thư giãn', 'bathroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(4, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/cover.jpg', 'Truc Bach Lake View Studio — Không gian bên hồ', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(4, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/bedroom.jpg', 'Truc Bach Lake View Studio — Giường ngủ sáng tự nhiên', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(4, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/lake-view.jpg', 'Truc Bach Lake View Studio — Mặt hồ Trúc Bạch và thuyền thiên nga', 'view', 2, FALSE, '2026-08-17 13:01:00+07'),
(4, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/bathroom.jpg', 'Truc Bach Lake View Studio — Phòng tắm có bồn', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(5, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Truc Bach Balcony Room — Ban công nhiều cây xanh', 'balcony', 0, TRUE, '2026-08-17 13:01:00+07'),
(5, NULL, 'assets/images/homestays/truc-bach-balcony-room/bedroom.jpg', 'Truc Bach Balcony Room — Giường đôi', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(5, NULL, 'assets/images/homestays/truc-bach-balcony-room/bathroom.jpg', 'Truc Bach Balcony Room — Phòng tắm', 'bathroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(6, NULL, 'assets/images/homestays/ba-dinh-family-apartment/cover.jpg', 'Ba Dinh Family Apartment — Phòng khách và bếp', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(6, NULL, 'assets/images/homestays/ba-dinh-family-apartment/bedroom.jpg', 'Ba Dinh Family Apartment — Phòng ngủ chính, giường đôi', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(6, NULL, 'assets/images/homestays/ba-dinh-family-apartment/second-bedroom.jpg', 'Ba Dinh Family Apartment — Phòng ngủ thứ hai, 2 giường đơn', 'bedroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(6, NULL, 'assets/images/homestays/ba-dinh-family-apartment/bathroom.jpg', 'Ba Dinh Family Apartment — Phòng tắm', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(7, NULL, 'assets/images/homestays/west-lake-sunset-studio/cover.jpg', 'West Lake Sunset Studio — Không gian nhìn ra hồ', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(7, NULL, 'assets/images/homestays/west-lake-sunset-studio/bedroom.jpg', 'West Lake Sunset Studio — Giường ngủ', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(7, NULL, 'assets/images/homestays/premium-bathroom/bathtub.jpg', 'West Lake Sunset Studio — Bồn tắm', 'bathroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(8, NULL, 'assets/images/homestays/west-lake-workcation-loft/cover.jpg', 'West Lake Workcation Loft — Không gian view hồ', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(8, NULL, 'assets/images/homestays/west-lake-workcation-loft/bedroom-workspace.jpg', 'West Lake Workcation Loft — Giường và bàn làm việc', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(8, NULL, 'assets/images/homestays/west-lake-workcation-loft/workspace.jpg', 'West Lake Workcation Loft — Góc làm việc riêng', 'workspace', 2, FALSE, '2026-08-17 13:01:00+07'),
(8, NULL, 'assets/images/homestays/west-lake-workcation-loft/bathroom.jpg', 'West Lake Workcation Loft — Phòng tắm hiện đại', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(9, NULL, 'assets/images/homestays/west-lake-family-apartment/cover.jpg', 'West Lake Family Apartment — Phòng khách và bếp', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(9, NULL, 'assets/images/homestays/west-lake-family-apartment/bedroom.jpg', 'West Lake Family Apartment — Phòng ngủ chính', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(9, NULL, 'assets/images/homestays/west-lake-family-apartment/second-bedroom.jpg', 'West Lake Family Apartment — Phòng ngủ thứ hai, 2 giường đơn', 'bedroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(9, NULL, 'assets/images/homestays/experiences/west-lake-view.jpg', 'West Lake Family Apartment — Hồ Tây trong khu vực lưu trú', 'other', 3, FALSE, '2026-08-17 13:01:00+07'),
(9, NULL, 'assets/images/homestays/west-lake-family-apartment/bathroom.jpg', 'West Lake Family Apartment — Phòng tắm có bồn', 'bathroom', 4, FALSE, '2026-08-17 13:01:00+07'),
(10, NULL, 'assets/images/homestays/dong-da-cozy-studio/cover.jpg', 'Dong Da Cozy Studio — Không gian chính ấm áp', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(10, NULL, 'assets/images/homestays/dong-da-cozy-studio/bedroom.jpg', 'Dong Da Cozy Studio — Giường đôi trong không gian ấm cúng', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(10, NULL, 'assets/images/homestays/dong-da-cozy-studio/bathroom.jpg', 'Dong Da Cozy Studio — Phòng tắm', 'bathroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(11, NULL, 'assets/images/homestays/dong-da-urban-loft/cover.jpg', 'Dong Da Urban Loft — Không gian loft và khu ngủ', 'bedroom', 0, TRUE, '2026-08-17 13:01:00+07'),
(11, NULL, 'assets/images/homestays/dong-da-urban-loft/workspace.jpg', 'Dong Da Urban Loft — Góc làm việc sáng tự nhiên', 'workspace', 1, FALSE, '2026-08-17 13:01:00+07'),
(11, NULL, 'assets/images/homestays/dong-da-urban-loft/bathroom.jpg', 'Dong Da Urban Loft — Phòng tắm', 'bathroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(12, NULL, 'assets/images/homestays/dong-da-garden-apartment/cover.jpg', 'Dong Da Garden Apartment — Phòng khách', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(12, NULL, 'assets/images/homestays/dong-da-garden-apartment/bedroom.jpg', 'Dong Da Garden Apartment — Giường chính', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(12, NULL, 'assets/images/homestays/dong-da-garden-apartment/sofa-bed.jpg', 'Dong Da Garden Apartment — Sofa bed cho khách thứ ba', 'living_room', 2, FALSE, '2026-08-17 13:01:00+07'),
(12, NULL, 'assets/images/homestays/dong-da-garden-apartment/balcony.jpg', 'Dong Da Garden Apartment — Ban công xanh và view thành phố', 'balcony', 3, FALSE, '2026-08-17 13:01:00+07'),
(12, NULL, 'assets/images/homestays/dong-da-garden-apartment/bathroom.jpg', 'Dong Da Garden Apartment — Phòng tắm', 'bathroom', 4, FALSE, '2026-08-17 13:01:00+07'),
(13, NULL, 'assets/images/homestays/cau-giay-modern-studio/cover.jpg', 'Cau Giay Modern Studio — Không gian sinh hoạt', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(13, NULL, 'assets/images/homestays/cau-giay-modern-studio/bedroom.jpg', 'Cau Giay Modern Studio — Giường đôi và quầy bếp studio', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(13, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Cau Giay Modern Studio — Phòng tắm', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/cover.jpg', 'Cau Giay Business Apartment — Phòng khách và bếp', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/bedroom.jpg', 'Cau Giay Business Apartment — Phòng ngủ và giường chính', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/kitchen.jpg', 'Cau Giay Business Apartment — Bếp', 'kitchen', 2, FALSE, '2026-08-17 13:01:00+07'),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/bathroom.jpg', 'Cau Giay Business Apartment — Phòng tắm', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/living-room.jpg', 'Cau Giay Business Apartment — Góc phòng khách', 'living_room', 4, FALSE, '2026-08-17 13:01:00+07'),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/workspace.jpg', 'Cau Giay Business Apartment — Góc làm việc', 'workspace', 5, FALSE, '2026-08-17 13:01:00+07'),
(15, NULL, 'assets/images/homestays/cau-giay-corner-studio/cover.jpg', 'Cau Giay Corner Studio — Không gian sinh hoạt sáng', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(15, NULL, 'assets/images/homestays/cau-giay-corner-studio/bedroom.jpg', 'Cau Giay Corner Studio — Giường đôi và quầy bếp studio', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(15, NULL, 'assets/images/homestays/cau-giay-corner-studio/balcony.jpg', 'Cau Giay Corner Studio — Ban công và góc ngồi', 'balcony', 2, FALSE, '2026-08-17 13:01:00+07'),
(15, NULL, 'assets/images/homestays/cau-giay-corner-studio/bathroom.jpg', 'Cau Giay Corner Studio — Phòng tắm', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07'),
(16, NULL, 'assets/images/homestays/cau-giay-family-suite/cover.jpg', 'Cau Giay Family Suite — Phòng khách và bếp', 'living_room', 0, TRUE, '2026-08-17 13:01:00+07'),
(16, NULL, 'assets/images/homestays/cau-giay-family-suite/bedroom.jpg', 'Cau Giay Family Suite — Phòng ngủ chính', 'bedroom', 1, FALSE, '2026-08-17 13:01:00+07'),
(16, NULL, 'assets/images/homestays/cau-giay-family-suite/second-bedroom.jpg', 'Cau Giay Family Suite — Phòng ngủ thứ hai với 2 giường', 'bedroom', 2, FALSE, '2026-08-17 13:01:00+07'),
(16, NULL, 'assets/images/homestays/cau-giay-family-suite/bathroom.jpg', 'Cau Giay Family Suite — Phòng tắm', 'bathroom', 3, FALSE, '2026-08-17 13:01:00+07');

COMMIT;
