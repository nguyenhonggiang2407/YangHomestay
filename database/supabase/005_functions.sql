-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — FUNCTIONS (PostgreSQL / Supabase)
--
-- Chỉ tạo các function an toàn, hữu ích:
--   1. set_updated_at()            — trigger tự cập nhật updated_at
--   2. is_unit_available()         — helper availability (overlap logic)
--
-- Không tạo stored procedure phức tạp không cần thiết.
-- ═════════════════════════════════════════════════════════════════════════

SET search_path TO public;

-- ═════════════════════════════════════════════════════════════════════════
-- 1. TRIGGER HELPER — set_updated_at()
-- ═════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$;

-- Áp trigger cho tất cả bảng có cột updated_at
CREATE TRIGGER trg_admins_updated_at
    BEFORE UPDATE ON public.admins
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_customers_updated_at
    BEFORE UPDATE ON public.customers
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_properties_updated_at
    BEFORE UPDATE ON public.properties
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_units_updated_at
    BEFORE UPDATE ON public.units
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_availability_blocks_updated_at
    BEFORE UPDATE ON public.availability_blocks
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_bookings_updated_at
    BEFORE UPDATE ON public.bookings
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_rate_rules_updated_at
    BEFORE UPDATE ON public.rate_rules
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_promotions_updated_at
    BEFORE UPDATE ON public.promotions
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_reviews_updated_at
    BEFORE UPDATE ON public.reviews
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_nearby_places_updated_at
    BEFORE UPDATE ON public.nearby_places
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE TRIGGER trg_contact_messages_updated_at
    BEFORE UPDATE ON public.contact_messages
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- ═════════════════════════════════════════════════════════════════════════
-- 2. AVAILABILITY HELPER — is_unit_available(unit_id, check_in, check_out)
--
-- Quy ước: check_in inclusive, check_out exclusive (20/08 → 23/08 = 3 đêm).
-- Unit khả dụng ⟺ KHÔNG có booking active chứa khoảng ngày VÀ
--                  KHÔNG có availability_block chứa khoảng ngày.
-- Booking blocking: pending | confirmed | checkin.
-- Không blocking:   checkout | cancelled.
--
-- Cách dùng (Phase 3, trong transaction trước khi INSERT booking):
--   SELECT public.is_unit_available(4, '2026-09-10', '2026-09-13');
--
-- SECURITY DEFINER: function chạy với quyền owner (bypass RLS) — bảo đảm
-- kết quả đúng ngay cả khi caller (anon/authenticated) không có policy SELECT.
-- ═════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION public.is_unit_available(
    p_unit_id   BIGINT,
    p_check_in  DATE,
    p_check_out DATE
)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
    SELECT NOT EXISTS (
        SELECT 1
        FROM public.bookings b
        WHERE b.unit_id = p_unit_id
          AND b.status IN ('pending', 'confirmed', 'checkin')
          AND p_check_in < b.check_out
          AND p_check_out > b.check_in
    )
    AND NOT EXISTS (
        SELECT 1
        FROM public.availability_blocks ab
        WHERE ab.unit_id = p_unit_id
          AND p_check_in < ab.end_date
          AND p_check_out > ab.start_date
    );
$$;

-- Kiểm tra nhanh (sau khi seed):
-- SELECT public.is_unit_available(1, '2026-08-10', '2026-08-13');  -- TRUE  (booking 5 đã checkout nên không chặn; block cleaning 17→18 ngoài khoảng)
-- SELECT public.is_unit_available(11, '2026-08-21', '2026-08-23'); -- FALSE (block maintenance 20→23/08 chứa 21, 22)
-- SELECT public.is_unit_available(1, '2026-08-18', '2026-08-20');  -- FALSE (block cleaning 17→18/08 chặn đêm 17)
-- SELECT public.is_unit_available(14, '2026-09-10', '2026-09-13'); -- TRUE
