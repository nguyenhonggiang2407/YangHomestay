-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — 009_security_hardening.sql
--
-- Mục tiêu:
--   1. Giữ toàn bộ Data API / RPC đi qua PHP backend dùng service_role.
--   2. Chặn anon/authenticated gọi trực tiếp SECURITY DEFINER RPC nhạy cảm.
--   3. Chuyển view rating sang quyền người gọi (security_invoker).
--   4. Cố định search_path cho trigger helper.
--
-- Migration này KHÔNG xóa dữ liệu và KHÔNG thay đổi logic booking/pricing.
-- Chạy SAU 008_real_homestay_images.sql.
-- ═════════════════════════════════════════════════════════════════════════

SET search_path TO public;

-- Supabase/PostgREST RPC: chỉ backend service_role được gọi trực tiếp.
REVOKE EXECUTE ON FUNCTION public.is_unit_available(BIGINT, DATE, DATE)
    FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.is_unit_available(BIGINT, DATE, DATE)
    TO service_role;

REVOKE EXECUTE ON FUNCTION public.available_units(DATE, DATE)
    FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.available_units(DATE, DATE)
    TO service_role;

REVOKE EXECUTE ON FUNCTION public.create_booking_atomic(
    BIGINT, BIGINT, DATE, DATE, SMALLINT, SMALLINT, TEXT, TEXT
) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.create_booking_atomic(
    BIGINT, BIGINT, DATE, DATE, SMALLINT, SMALLINT, TEXT, TEXT
) TO service_role;

-- Postgres 15+: view chạy với quyền caller. PHP service_role vẫn đọc được nhờ
-- BYPASSRLS, nhưng anon/authenticated không còn thừa hưởng quyền owner của view.
ALTER VIEW public.v_unit_rating SET (security_invoker = true);

-- Tránh mutable search_path trên trigger helper.
ALTER FUNCTION public.set_updated_at() SET search_path = public, pg_temp;

NOTIFY pgrst, 'reload schema';
