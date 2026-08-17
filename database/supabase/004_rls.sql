-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — ROW LEVEL SECURITY (RLS) — PostgreSQL / Supabase
--
-- Kiến trúc bảo mật hiện tại (GIỮ NGUYÊN, chưa đổi):
--
--     Browser → PHP backend → Supabase PostgreSQL
--
-- • PHP backend sẽ kết nối bằng SUPABASE_SERVICE_ROLE_KEY (hoặc user DB có
--   quyền owner) — role này có BYPASSRLS, nên RLS KHÔNG chặn backend.
-- • anon key / authenticated key KHÔNG có quyền gì trên các bảng này
--   (RLS enabled + KHÔNG policy nào) → an toàn mặc định.
-- • Chưa tạo bất kỳ policy public nào. Nếu sau này cần public read cho dữ
--   liệu listing an toàn (properties/units active, amenities, tags...),
--   hãy thêm policy SELECT có kiểm soát — KHÔNG tạo unrestricted INSERT/
--   UPDATE/DELETE policy.
--
-- KHÔNG lưu secret trong frontend: anon key có thể nằm ở frontend (công khai),
-- nhưng service_role key CHỈ dùng ở backend PHP.
-- ═════════════════════════════════════════════════════════════════════════

SET search_path TO public;

-- ═════════════════════════════════════════════════════════════════════════
-- Bật RLS trên TẤT CẢ bảng application (24 bảng)
-- ═════════════════════════════════════════════════════════════════════════
ALTER TABLE public.admins               ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.customers            ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.properties           ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.property_images      ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.units                ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.unit_images          ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.amenities            ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.unit_amenities       ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.experience_tags      ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.unit_experience_tags ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.availability_blocks  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.rate_rules           ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.promotions           ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.bookings             ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.booking_nights       ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.booking_price_items  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.promotion_usages     ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.favorites            ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.reviews              ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.nearby_places        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.notifications        ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.contact_messages     ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.login_logs           ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_logs           ENABLE ROW LEVEL SECURITY;

-- ═════════════════════════════════════════════════════════════════════════
-- KHÔNG tạo policy nào ở Phase 2 (giữ conservative).
--
-- Hệ quả mặc định:
--   • anon / authenticated → không SELECT/INSERT/UPDATE/DELETE được bảng nào.
--   • postgres / service_role / owner → bypass RLS, hoạt động bình thường
--     (đây là đường kết nối của PHP backend Phase 3).
--
-- Khi nào cần public read (VÍ DỤ — KHÔNG chạy ở Phase 2):
--
--   CREATE POLICY "public read active properties"
--     ON public.properties FOR SELECT
--     USING (status = 'active');
--
--   CREATE POLICY "public read active units"
--     ON public.units FOR SELECT
--     USING (status = 'active');
--
-- ═════════════════════════════════════════════════════════════════════════

-- Kiểm tra nhanh trạng thái RLS:
-- SELECT tablename, rowsecurity FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;
