-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — 006_booking_rpc.sql (ATOMIC BOOKING ENGINE)
--
-- • Chạy SAU 005_functions.sql (Supabase SQL Editor).
-- • Sau khi tạo function, nếu PostgREST chưa thấy RPC:
--     NOTIFY pgrst, 'reload schema';
--
-- Tạo:
--   1. SEQUENCE booking_reference_seq — sinh mã đặt chỗ concurrency-safe
--   2. create_booking_atomic() — tạo booking TRONG MỘT TRANSACTION
--
-- CONCURRENCY:
--   pg_advisory_xact_lock(theo unit_id) → 2 request cùng unit+dates:
--     A → SUCCESS, B → 409 CONFLICT. Không bao giờ cả hai success.
--
-- MÃ LỖI TÙY CHỈNH (SQLSTATE, PHP map sang HTTP status):
--   YB001 unit/property không tồn tại hoặc không active   → 404
--   YB002 ngày không hợp lệ (check_in quá khứ, out <= in) → 422
--   YB003 số khách vượt sức chứa                           → 422
--   YB004 booking chồng lấn (không available)              → 409
--   YB005 availability_block chặn ngày                    → 409
--   YB006 mã ưu đãi không hợp lệ / hết hạn / chưa đủ min  → 422
--   YB007 mã ưu đãi hết lượt dùng                         → 422
--   YB008 khách hàng không tồn tại / bị khóa               → 401
--
-- PRICING: tính từng đêm ngay trong transaction (nguồn sự thật của DB).
--   PHP không gửi giá — không trust client. Quy tắc khớp PricingService.php:
--   priority DESC → cụ thể hơn (có day_of_week; có start+end) → updated_at DESC → id DESC.
--   day_of_week: 1=CN ... 7=T7 (quy ước lưu trữ; EXTRACT(DOW)+1).
--   start_date inclusive / end_date EXCLUSIVE.
-- ═════════════════════════════════════════════════════════════════════════

SET search_path TO public;

-- ═════════════════════════════════════════════════════════════════════════
-- 1. SEQUENCE booking reference — 'YH' + YYYYMMDD + 4 số (nextval)
-- ═════════════════════════════════════════════════════════════════════════
CREATE SEQUENCE IF NOT EXISTS public.booking_reference_seq START 1;

-- ═════════════════════════════════════════════════════════════════════════
-- 2. create_booking_atomic()
-- ═════════════════════════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION public.create_booking_atomic(
    p_customer_id      BIGINT,
    p_unit_id          BIGINT,
    p_check_in         DATE,
    p_check_out        DATE,
    p_adults           SMALLINT DEFAULT 1,
    p_children         SMALLINT DEFAULT 0,
    p_special_requests TEXT DEFAULT NULL,
    p_promotion_code   TEXT DEFAULT NULL
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_unit              public.units%ROWTYPE;
    v_property          public.properties%ROWTYPE;
    v_customer          public.customers%ROWTYPE;
    v_promotion         public.promotions%ROWTYPE;
    v_promo_usage_count   INTEGER;
    v_promo_customer_count INTEGER;
    v_subtotal          BIGINT := 0;
    v_cleaning          BIGINT := 0;
    v_discount          BIGINT := 0;
    v_total             BIGINT := 0;
    v_guests            SMALLINT;
    v_nights            SMALLINT;
    v_reference         VARCHAR(20);
    v_booking_id        BIGINT;
    v_night             RECORD;
    v_rate              BIGINT;
    v_rule_id           BIGINT;
    v_nights_json       JSONB := '[]'::jsonb;
BEGIN
    -- 1) Khách hàng tồn tại + active
    SELECT * INTO v_customer FROM public.customers WHERE id = p_customer_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Customer not found' USING ERRCODE = 'YB008';
    END IF;
    IF v_customer.status <> 'active' THEN
        RAISE EXCEPTION 'Customer inactive' USING ERRCODE = 'YB008';
    END IF;

    -- 2) Unit + Property active
    SELECT * INTO v_unit FROM public.units WHERE id = p_unit_id;
    IF NOT FOUND OR v_unit.status <> 'active' THEN
        RAISE EXCEPTION 'Unit not found or inactive' USING ERRCODE = 'YB001';
    END IF;
    SELECT * INTO v_property FROM public.properties WHERE id = v_unit.property_id;
    IF NOT FOUND OR v_property.status <> 'active' THEN
        RAISE EXCEPTION 'Property not active' USING ERRCODE = 'YB001';
    END IF;

    -- 3) Ngày: check_in inclusive / check_out exclusive, không đặt quá khứ
    IF p_check_out <= p_check_in THEN
        RAISE EXCEPTION 'Invalid date range' USING ERRCODE = 'YB002';
    END IF;
    IF p_check_in < current_date THEN
        RAISE EXCEPTION 'Check-in must be today or later' USING ERRCODE = 'YB002';
    END IF;

    -- 4) Khách
    IF p_adults < 1 OR p_children < 0 THEN
        RAISE EXCEPTION 'Invalid guests' USING ERRCODE = 'YB003';
    END IF;
    v_guests := p_adults + p_children;
    IF v_guests > v_unit.max_guests THEN
        RAISE EXCEPTION 'Guests exceed capacity' USING ERRCODE = 'YB003';
    END IF;

    -- 5) KHÓA THEO UNIT — chống concurrent booking (2 request cùng unit)
    PERFORM pg_advisory_xact_lock(hashtext('yanghomestay_booking'), p_unit_id::integer);

    -- 6) Overlap booking active (pending|confirmed|checkin)
    IF EXISTS (
        SELECT 1 FROM public.bookings b
        WHERE b.unit_id = p_unit_id
          AND b.status IN ('pending', 'confirmed', 'checkin')
          AND p_check_in < b.check_out
          AND p_check_out > b.check_in
    ) THEN
        RAISE EXCEPTION 'Unit not available for these dates' USING ERRCODE = 'YB004';
    END IF;

    -- 7) Availability blocks (start inclusive / end exclusive)
    IF EXISTS (
        SELECT 1 FROM public.availability_blocks ab
        WHERE ab.unit_id = p_unit_id
          AND p_check_in < ab.end_date
          AND p_check_out > ab.start_date
    ) THEN
        RAISE EXCEPTION 'Unit blocked for these dates' USING ERRCODE = 'YB005';
    END IF;

    -- 8) PRICING — tính từng đêm (snapshot), nguồn sự thật = DB
    v_cleaning := v_unit.cleaning_fee;
    v_nights   := (p_check_out - p_check_in);

    FOR v_night IN
        SELECT d::date AS stay_date
        FROM generate_series(p_check_in, p_check_out - 1, INTERVAL '1 day') AS d
    LOOP
        v_rule_id := NULL;
        v_rate    := v_unit.base_price;

        SELECT rr.id, rr.price INTO v_rule_id, v_rate
        FROM public.rate_rules rr
        WHERE rr.unit_id = p_unit_id
          AND rr.active
          AND (rr.start_date  IS NULL OR v_night.stay_date >= rr.start_date)
          AND (rr.end_date    IS NULL OR v_night.stay_date <  rr.end_date)   -- end EXCLUSIVE
          AND (rr.day_of_week IS NULL OR rr.day_of_week = EXTRACT(DOW FROM v_night.stay_date)::smallint + 1)
        ORDER BY
            rr.priority DESC,
            (CASE WHEN rr.day_of_week IS NOT NULL THEN 1 ELSE 0 END) DESC,
            (CASE WHEN rr.start_date IS NOT NULL AND rr.end_date IS NOT NULL THEN 1
                  WHEN rr.start_date IS NOT NULL OR rr.end_date IS NOT NULL THEN 0.5
                  ELSE 0 END) DESC,
            rr.updated_at DESC,
            rr.id DESC
        LIMIT 1;

        IF v_rule_id IS NULL THEN
            v_rate := v_unit.base_price;
        END IF;

        v_subtotal := v_subtotal + v_rate;
        v_nights_json := v_nights_json || jsonb_build_object(
            'stay_date',     v_night.stay_date,
            'nightly_rate',  v_rate,
            'rate_rule_id',  v_rule_id
        );
    END LOOP;

    -- 9) PROMOTION (validate atomic với usage)
    v_discount := 0;
    IF p_promotion_code IS NOT NULL AND btrim(p_promotion_code) <> '' THEN
        SELECT * INTO v_promotion
        FROM public.promotions
        WHERE code = btrim(p_promotion_code)
          AND active
          AND valid_from <= current_date
          AND valid_until >= current_date;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Invalid or expired promotion' USING ERRCODE = 'YB006';
        END IF;

        IF v_promotion.minimum_booking_value IS NOT NULL
           AND v_subtotal < v_promotion.minimum_booking_value THEN
            RAISE EXCEPTION 'Booking value below minimum for promotion' USING ERRCODE = 'YB006';
        END IF;

        SELECT count(*) INTO v_promo_usage_count
        FROM public.promotion_usages WHERE promotion_id = v_promotion.id;
        IF v_promotion.usage_limit IS NOT NULL
           AND v_promo_usage_count >= v_promotion.usage_limit THEN
            RAISE EXCEPTION 'Promotion usage limit reached' USING ERRCODE = 'YB007';
        END IF;

        SELECT count(*) INTO v_promo_customer_count
        FROM public.promotion_usages
        WHERE promotion_id = v_promotion.id AND customer_id = p_customer_id;
        IF v_promotion.usage_limit_per_customer IS NOT NULL
           AND v_promo_customer_count >= v_promotion.usage_limit_per_customer THEN
            RAISE EXCEPTION 'Promotion already used by this customer' USING ERRCODE = 'YB007';
        END IF;

        IF v_promotion.discount_type = 'percentage' THEN
            v_discount := round(v_subtotal * v_promotion.discount_value / 100.0);
            IF v_promotion.maximum_discount IS NOT NULL
               AND v_discount > v_promotion.maximum_discount THEN
                v_discount := v_promotion.maximum_discount;
            END IF;
        ELSE
            v_discount := v_promotion.discount_value;
            IF v_promotion.maximum_discount IS NOT NULL
               AND v_discount > v_promotion.maximum_discount THEN
                v_discount := v_promotion.maximum_discount;
            END IF;
        END IF;
    END IF;

    -- Discount không được làm total âm
    v_total := v_subtotal + v_cleaning - v_discount;
    IF v_total < 0 THEN
        v_total := 0;
    END IF;

    -- 10) Booking reference — concurrency-safe qua sequence
    v_reference := 'YH' || to_char(current_date, 'YYYYMMDD')
                   || lpad(nextval('public.booking_reference_seq')::text, 4, '0');

    -- 11) INSERT booking (snapshot guest từ customers; không nhận từ client)
    INSERT INTO public.bookings (
        booking_reference, customer_id, unit_id,
        guest_name, guest_email, guest_phone,
        check_in, check_out, number_of_nights,
        adults, children, total_guests,
        subtotal, cleaning_fee, discount_amount, total_amount, currency,
        promotion_id, promotion_code_snapshot,
        status, payment_status, special_requests,
        created_at
    ) VALUES (
        v_reference, p_customer_id, p_unit_id,
        v_customer.full_name, v_customer.email, v_customer.phone,
        p_check_in, p_check_out, v_nights,
        p_adults, p_children, v_guests,
        v_subtotal, v_cleaning, v_discount, v_total, 'VND',
        CASE WHEN v_discount > 0 THEN v_promotion.id ELSE NULL END,
        CASE WHEN v_discount > 0 THEN btrim(p_promotion_code) ELSE NULL END,
        'pending', 'unpaid', p_special_requests,
        now()
    ) RETURNING id INTO v_booking_id;

    -- 12) INSERT booking_nights (price snapshot từng đêm)
    INSERT INTO public.booking_nights (booking_id, stay_date, nightly_rate, rate_rule_id, created_at)
    SELECT v_booking_id,
           (n.value ->> 'stay_date')::date,
           (n.value ->> 'nightly_rate')::bigint,
           (n.value ->> 'rate_rule_id')::bigint,
           now()
    FROM jsonb_array_elements(v_nights_json) AS n;

    -- 13) INSERT booking_price_items (breakdown hiển thị hóa đơn)
    INSERT INTO public.booking_price_items (booking_id, item_type, label, amount, display_order, created_at) VALUES
    (v_booking_id, 'accommodation', 'Tiền phòng (' || v_nights || ' đêm)', v_subtotal, 1, now());

    IF v_cleaning > 0 THEN
        INSERT INTO public.booking_price_items (booking_id, item_type, label, amount, display_order, created_at) VALUES
        (v_booking_id, 'cleaning_fee', 'Phí vệ sinh', v_cleaning, 2, now());
    END IF;

    IF v_discount > 0 THEN
        INSERT INTO public.booking_price_items (booking_id, item_type, label, amount, display_order, created_at) VALUES
        (v_booking_id, 'discount', 'Ưu đãi (' || btrim(p_promotion_code) || ')', -v_discount, 3, now());
    END IF;

    -- 14) INSERT promotion_usage (nếu có)
    IF v_discount > 0 THEN
        INSERT INTO public.promotion_usages (promotion_id, customer_id, booking_id, discount_amount, used_at)
        VALUES (v_promotion.id, p_customer_id, v_booking_id, v_discount, now());
    END IF;

    -- 15) TRẢ VỀ booking + nights (JSONB)
    RETURN jsonb_build_object(
        'id',                 v_booking_id,
        'booking_reference',  v_reference,
        'customer_id',        p_customer_id,
        'unit_id',            p_unit_id,
        'unit_name',          v_unit.name,
        'property_name',      v_property.name,
        'check_in',           p_check_in,
        'check_out',          p_check_out,
        'number_of_nights',   v_nights,
        'adults',             p_adults,
        'children',           p_children,
        'total_guests',       v_guests,
        'subtotal',           v_subtotal,
        'cleaning_fee',       v_cleaning,
        'discount_amount',    v_discount,
        'total_amount',       v_total,
        'currency',           'VND',
        'promotion_code',     CASE WHEN v_discount > 0 THEN btrim(p_promotion_code) ELSE NULL END,
        'status',             'pending',
        'payment_status',     'unpaid',
        'nights',             v_nights_json
    );
END;
$$;

-- Kiểm tra nhanh (SAU seed — cần customer/unit tồn tại):
-- SELECT public.create_booking_atomic(8, 13, '2026-09-10', '2026-09-13', 2, 0, 'Test booking', 'YANGNEW10');
-- → thành công (unit 13 Cau Giay Modern Studio chưa bị chặn 10-13/09)
-- SELECT public.create_booking_atomic(8, 13, '2026-09-10', '2026-09-13', 2, 0, NULL, NULL);
-- → YB004 conflict (chồng lấn với booking vừa tạo)
-- CHÚ Ý: test booking sẽ được insert — sau khi test nên xóa qua SQL hoặc để demo.
