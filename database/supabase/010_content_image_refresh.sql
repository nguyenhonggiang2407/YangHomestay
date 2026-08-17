-- ═════════════════════════════════════════════════════════════════════
-- 010_CONTENT_IMAGE_REFRESH — 2026-08-17
-- Dành cho database đã deploy: sửa gallery local, duplicate mapping,
-- và ảnh nearby sai chủ đề (food/coffee/landmark).
-- Có thể chạy lại an toàn.
-- ═════════════════════════════════════════════════════════════════════

BEGIN;

-- Curated local image refresh for YangHomestay.
-- Xóa mapping local cũ/trùng nhưng giữ lại row upload ngoài assets/images/.
DELETE FROM public.unit_images
WHERE unit_id BETWEEN 1 AND 16
  AND image_url LIKE 'assets/images/%';

DELETE FROM public.property_images
WHERE property_id BETWEEN 1 AND 5
  AND image_url LIKE 'assets/images/%';

-- Dọn duplicate lịch sử trước khi tạo uniqueness.
DELETE FROM public.unit_images a
USING public.unit_images b
WHERE a.id > b.id
  AND a.unit_id = b.unit_id
  AND a.image_url = b.image_url;

DELETE FROM public.property_images a
USING public.property_images b
WHERE a.id > b.id
  AND a.property_id = b.property_id
  AND a.image_url = b.image_url;

CREATE UNIQUE INDEX IF NOT EXISTS uq_unit_images_unit_url
  ON public.unit_images (unit_id, image_url);
CREATE UNIQUE INDEX IF NOT EXISTS uq_property_images_property_url
  ON public.property_images (property_id, image_url);

INSERT INTO public.unit_images
(unit_id, storage_path, image_url, caption, image_category, display_order, is_cover) VALUES
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/cover.jpg', 'Old Quarter Balcony Studio — Không gian chính', 'bedroom', 0, TRUE),
(1, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Old Quarter Balcony Studio — Ban công xanh', 'balcony', 1, FALSE),
(1, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Old Quarter Balcony Studio — Phòng tắm', 'bathroom', 2, FALSE),
(2, NULL, 'assets/images/homestays/hanoi-vintage-loft/cover.jpg', 'Hanoi Vintage Loft — Không gian chính', 'living_room', 0, TRUE),
(2, NULL, 'assets/images/homestays/hanoi-vintage-loft/bedroom.jpg', 'Hanoi Vintage Loft — Phòng ngủ vintage', 'bedroom', 1, FALSE),
(2, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Hanoi Vintage Loft — Phòng tắm', 'bathroom', 2, FALSE),
(3, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/cover.jpg', 'Hoan Kiem Couple Studio — Phòng ngủ chính', 'bedroom', 0, TRUE),
(3, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/bedroom-2.jpg', 'Hoan Kiem Couple Studio — Góc nghỉ sáng tự nhiên', 'bedroom', 1, FALSE),
(3, NULL, 'assets/images/homestays/premium-bathroom/bathtub.jpg', 'Hoan Kiem Couple Studio — Phòng tắm', 'bathroom', 2, FALSE),
(4, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/cover.jpg', 'Truc Bach Lake View Studio — Không gian bên hồ', 'bedroom', 0, TRUE),
(4, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/window-view.jpg', 'Truc Bach Lake View Studio — Góc nhìn bên cửa sổ', 'view', 1, FALSE),
(4, NULL, 'assets/images/homestays/premium-bathroom/bathtub.jpg', 'Truc Bach Lake View Studio — Phòng tắm', 'bathroom', 2, FALSE),
(5, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Truc Bach Balcony Room — Ban công nhiều cây xanh', 'balcony', 0, TRUE),
(5, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/bedroom-2.jpg', 'Truc Bach Balcony Room — Phòng ngủ sáng', 'bedroom', 1, FALSE),
(5, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Truc Bach Balcony Room — Phòng tắm', 'bathroom', 2, FALSE),
(6, NULL, 'assets/images/homestays/ba-dinh-family-apartment/cover.jpg', 'Ba Dinh Family Apartment — Phòng khách và bếp', 'living_room', 0, TRUE),
(6, NULL, 'assets/images/homestays/ba-dinh-family-apartment/bedroom.jpg', 'Ba Dinh Family Apartment — Phòng ngủ', 'bedroom', 1, FALSE),
(6, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Ba Dinh Family Apartment — Phòng tắm', 'bathroom', 2, FALSE),
(7, NULL, 'assets/images/homestays/west-lake-sunset-studio/cover.jpg', 'West Lake Sunset Studio — Không gian nhìn ra hồ', 'living_room', 0, TRUE),
(7, NULL, 'assets/images/homestays/west-lake-sunset-studio/lake-view.jpg', 'West Lake Sunset Studio — Góc view hồ', 'view', 1, FALSE),
(7, NULL, 'assets/images/homestays/premium-bathroom/bathtub.jpg', 'West Lake Sunset Studio — Phòng tắm', 'bathroom', 2, FALSE),
(8, NULL, 'assets/images/homestays/west-lake-workcation-loft/cover.jpg', 'West Lake Workcation Loft — Phòng khách', 'living_room', 0, TRUE),
(8, NULL, 'assets/images/homestays/west-lake-workcation-loft/workspace.jpg', 'West Lake Workcation Loft — Góc làm việc', 'workspace', 1, FALSE),
(8, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'West Lake Workcation Loft — Phòng tắm', 'bathroom', 2, FALSE),
(9, NULL, 'assets/images/homestays/west-lake-family-apartment/cover.jpg', 'West Lake Family Apartment — Phòng khách và bếp', 'living_room', 0, TRUE),
(9, NULL, 'assets/images/homestays/ba-dinh-family-apartment/bedroom.jpg', 'West Lake Family Apartment — Phòng ngủ', 'bedroom', 1, FALSE),
(9, NULL, 'assets/images/homestays/west-lake-sunset-studio/lake-view.jpg', 'West Lake Family Apartment — Góc nhìn thoáng', 'view', 2, FALSE),
(9, NULL, 'assets/images/homestays/premium-bathroom/bathtub.jpg', 'West Lake Family Apartment — Phòng tắm', 'bathroom', 3, FALSE),
(10, NULL, 'assets/images/homestays/dong-da-cozy-studio/cover.jpg', 'Dong Da Cozy Studio — Không gian chính ấm áp', 'living_room', 0, TRUE),
(10, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/bedroom-2.jpg', 'Dong Da Cozy Studio — Góc ngủ sáng tự nhiên', 'bedroom', 1, FALSE),
(10, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Dong Da Cozy Studio — Phòng tắm', 'bathroom', 2, FALSE),
(11, NULL, 'assets/images/homestays/dong-da-urban-loft/cover.jpg', 'Dong Da Urban Loft — Không gian loft', 'living_room', 0, TRUE),
(11, NULL, 'assets/images/homestays/cau-giay-business-apartment/workspace.jpg', 'Dong Da Urban Loft — Góc làm việc', 'workspace', 1, FALSE),
(11, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Dong Da Urban Loft — Phòng tắm', 'bathroom', 2, FALSE),
(12, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Dong Da Garden Apartment — Ban công xanh', 'balcony', 0, TRUE),
(12, NULL, 'assets/images/homestays/ba-dinh-family-apartment/cover.jpg', 'Dong Da Garden Apartment — Phòng khách', 'living_room', 1, FALSE),
(12, NULL, 'assets/images/homestays/ba-dinh-family-apartment/bedroom.jpg', 'Dong Da Garden Apartment — Phòng ngủ', 'bedroom', 2, FALSE),
(12, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Dong Da Garden Apartment — Phòng tắm', 'bathroom', 3, FALSE),
(13, NULL, 'assets/images/homestays/cau-giay-modern-studio/cover.jpg', 'Cau Giay Modern Studio — Không gian chính', 'living_room', 0, TRUE),
(13, NULL, 'assets/images/homestays/cau-giay-business-apartment/workspace.jpg', 'Cau Giay Modern Studio — Góc làm việc', 'workspace', 1, FALSE),
(13, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Cau Giay Modern Studio — Phòng tắm', 'bathroom', 2, FALSE),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/cover.jpg', 'Cau Giay Business Apartment — Không gian chính', 'living_room', 0, TRUE),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/living-room.jpg', 'Cau Giay Business Apartment — Phòng khách', 'living_room', 1, FALSE),
(14, NULL, 'assets/images/homestays/cau-giay-business-apartment/workspace.jpg', 'Cau Giay Business Apartment — Góc làm việc', 'workspace', 2, FALSE),
(14, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Cau Giay Business Apartment — Phòng tắm', 'bathroom', 3, FALSE),
(15, NULL, 'assets/images/homestays/hoan-kiem-couple-studio/bedroom-2.jpg', 'Cau Giay Corner Studio — Phòng ngủ', 'bedroom', 0, TRUE),
(15, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Cau Giay Corner Studio — Góc ban công', 'balcony', 1, FALSE),
(15, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Cau Giay Corner Studio — Phòng tắm', 'bathroom', 2, FALSE),
(16, NULL, 'assets/images/homestays/west-lake-family-apartment/cover.jpg', 'Cau Giay Family Suite — Phòng khách và bếp', 'living_room', 0, TRUE),
(16, NULL, 'assets/images/homestays/ba-dinh-family-apartment/cover.jpg', 'Cau Giay Family Suite — Không gian sinh hoạt', 'living_room', 1, FALSE),
(16, NULL, 'assets/images/homestays/ba-dinh-family-apartment/bedroom.jpg', 'Cau Giay Family Suite — Phòng ngủ', 'bedroom', 2, FALSE),
(16, NULL, 'assets/images/homestays/premium-bathroom/light-bathroom.jpg', 'Cau Giay Family Suite — Phòng tắm', 'bathroom', 3, FALSE)
ON CONFLICT (unit_id, image_url) DO UPDATE SET
  caption = EXCLUDED.caption,
  image_category = EXCLUDED.image_category,
  display_order = EXCLUDED.display_order,
  is_cover = EXCLUDED.is_cover;


INSERT INTO public.property_images
(property_id, storage_path, image_url, caption, image_category, display_order, is_cover) VALUES
(1, NULL, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Mặt tiền YangHomestay Phố Cổ', 'exterior', 0, TRUE),
(1, NULL, 'assets/images/homestays/old-quarter-balcony-studio/cover.jpg', 'Không gian đặc trưng YangHomestay Phố Cổ', 'common_area', 1, FALSE),
(1, NULL, 'assets/images/homestays/hanoi-vintage-loft/cover.jpg', 'Không gian vintage YangHomestay Phố Cổ', 'common_area', 2, FALSE),
(2, NULL, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Mặt tiền YangHomestay Trúc Bạch', 'exterior', 0, TRUE),
(2, NULL, 'assets/images/homestays/truc-bach-lake-view-studio/cover.jpg', 'Không gian nhìn hồ YangHomestay Trúc Bạch', 'view', 1, FALSE),
(2, NULL, 'assets/images/homestays/truc-bach-balcony-room/cover.jpg', 'Ban công xanh YangHomestay Trúc Bạch', 'common_area', 2, FALSE),
(3, NULL, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Mặt tiền YangHomestay Tây Hồ', 'exterior', 0, TRUE),
(3, NULL, 'assets/images/homestays/west-lake-sunset-studio/cover.jpg', 'Không gian nhìn hồ YangHomestay Tây Hồ', 'view', 1, FALSE),
(3, NULL, 'assets/images/homestays/west-lake-workcation-loft/cover.jpg', 'Không gian workcation YangHomestay Tây Hồ', 'common_area', 2, FALSE),
(4, NULL, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Mặt tiền YangHomestay Đống Đa', 'exterior', 0, TRUE),
(4, NULL, 'assets/images/homestays/dong-da-cozy-studio/cover.jpg', 'Không gian ấm áp YangHomestay Đống Đa', 'common_area', 1, FALSE),
(4, NULL, 'assets/images/homestays/dong-da-urban-loft/cover.jpg', 'Không gian urban YangHomestay Đống Đa', 'common_area', 2, FALSE),
(5, NULL, 'assets/images/homestays/hanoi-exterior/hanoi-apartment-exterior.jpg', 'Mặt tiền YangHomestay Cầu Giấy', 'exterior', 0, TRUE),
(5, NULL, 'assets/images/homestays/cau-giay-modern-studio/cover.jpg', 'Không gian hiện đại YangHomestay Cầu Giấy', 'common_area', 1, FALSE),
(5, NULL, 'assets/images/homestays/cau-giay-business-apartment/cover.jpg', 'Không gian business YangHomestay Cầu Giấy', 'common_area', 2, FALSE)
ON CONFLICT (property_id, image_url) DO UPDATE SET
  caption = EXCLUDED.caption,
  image_category = EXCLUDED.image_category,
  display_order = EXCLUDED.display_order,
  is_cover = EXCLUDED.is_cover;


UPDATE public.nearby_places SET image_url='assets/images/areas/hoan-kiem-lake.jpg' WHERE id=1;
UPDATE public.nearby_places SET image_url='assets/images/experiences/hanoi-food/vietnamese-meal.jpg' WHERE id=2;
UPDATE public.nearby_places SET image_url='assets/images/areas/old-quarter.jpg' WHERE id=3;
UPDATE public.nearby_places SET image_url='assets/images/areas/west-lake.jpg' WHERE id=4;
UPDATE public.nearby_places SET image_url='assets/images/experiences/hanoi-food/pho.jpg' WHERE id=5;
UPDATE public.nearby_places SET image_url='assets/images/areas/west-lake.jpg' WHERE id=6;
UPDATE public.nearby_places SET image_url='assets/images/experiences/hanoi-food/egg-coffee.jpg' WHERE id=7;
UPDATE public.nearby_places SET image_url='assets/images/areas/temple-of-literature.jpg' WHERE id=8;
UPDATE public.nearby_places SET image_url='assets/images/areas/hanoi-train-street.jpg' WHERE id=9;
UPDATE public.nearby_places SET image_url='assets/images/experiences/hanoi-food/egg-coffee.jpg' WHERE id=11;

COMMIT;
