-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — 007_search_support.sql (SEARCH / RATING SUPPORT)
--
-- • Chạy SAU 006_booking_rpc.sql.
-- • Sau khi tạo, nếu PostgREST chưa thấy:  NOTIFY pgrst, 'reload schema';
--
-- Tạo:
--   1. v_unit_rating — điểm trung bình + số review ĐÃ DUYỆT theo unit
--      (backend join trong PHP: /rest/v1/v_unit_rating?unit_id=in.(1,2,3))
--   2. available_units() — trả danh sách unit_id khả dụng cho khoảng ngày
--      (search theo check_in/check_out; dùng chung overlap logic)
-- ═════════════════════════════════════════════════════════════════════════

SET search_path TO public;

-- ═════════════════════════════════════════════════════════════════════════
-- 1. VIEW v_unit_rating — chỉ tính review status = 'approved'
-- ═════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE VIEW public.v_unit_rating AS
SELECT
    u.id AS unit_id,
    COALESCE(ROUND(AVG(r.overall_rating)::numeric, 1), 0)::numeric(3,1) AS avg_rating,
    COUNT(r.id)::integer AS review_count
FROM public.units u
LEFT JOIN public.reviews r
    ON r.unit_id = u.id
   AND r.status = 'approved'
GROUP BY u.id;

-- Kiểm tra: SELECT * FROM public.v_unit_rating ORDER BY unit_id;

-- ═════════════════════════════════════════════════════════════════════════
-- 2. FUNCTION available_units(p_check_in, p_check_out)
--    Trả unit_id active, KHÔNG chồng lấn booking active, KHÔNG bị block.
--    Quy ước: check_in inclusive / check_out exclusive.
-- ═════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION public.available_units(
    p_check_in  DATE,
    p_check_out DATE
)
RETURNS TABLE(unit_id BIGINT)
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
    SELECT u.id
    FROM public.units u
    JOIN public.properties p ON p.id = u.property_id
    WHERE u.status = 'active'
      AND p.status = 'active'
      AND NOT EXISTS (
          SELECT 1 FROM public.bookings b
          WHERE b.unit_id = u.id
            AND b.status IN ('pending', 'confirmed', 'checkin')
            AND p_check_in < b.check_out
            AND p_check_out > b.check_in
      )
      AND NOT EXISTS (
          SELECT 1 FROM public.availability_blocks ab
          WHERE ab.unit_id = u.id
            AND p_check_in < ab.end_date
            AND p_check_out > ab.start_date
      );
$$;

-- Kiểm tra: SELECT * FROM public.available_units('2026-09-10', '2026-09-13') ORDER BY unit_id;
