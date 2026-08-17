-- ═════════════════════════════════════════════════════════════════════════
-- YANGHOMESTAY — INDEXES (PostgreSQL / Supabase)
--
-- • Chạy SAU 001_schema.sql (+ 002_seed.sql nếu cần).
-- • PK / UNIQUE đã tạo index tự động ngay trong table (001_schema.sql).
--   File này chỉ bổ sung secondary indexes phục vụ search/booking/report.
-- • Dùng IF NOT EXISTS để chạy lại an toàn.
-- ═════════════════════════════════════════════════════════════════════════

SET search_path TO public;

-- ═════════════════════════════════════════════════════════════════════════
-- SEARCH — properties / units
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_properties_district  ON public.properties (district);
CREATE INDEX IF NOT EXISTS idx_properties_status    ON public.properties (status);
CREATE INDEX IF NOT EXISTS idx_properties_featured  ON public.properties (featured);

CREATE INDEX IF NOT EXISTS idx_units_property       ON public.units (property_id);
CREATE INDEX IF NOT EXISTS idx_units_status         ON public.units (status);
CREATE INDEX IF NOT EXISTS idx_units_base_price     ON public.units (base_price);
CREATE INDEX IF NOT EXISTS idx_units_max_guests     ON public.units (max_guests);
CREATE INDEX IF NOT EXISTS idx_units_featured       ON public.units (featured);

-- ═════════════════════════════════════════════════════════════════════════
-- IMAGES — thứ tự hiển thị
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_property_images_property ON public.property_images (property_id, display_order);
CREATE INDEX IF NOT EXISTS idx_unit_images_unit_order   ON public.unit_images (unit_id, display_order);

-- ═════════════════════════════════════════════════════════════════════════
-- BOOKINGS — phục vụ quản lý + availability overlap
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_bookings_customer    ON public.bookings (customer_id);
CREATE INDEX IF NOT EXISTS idx_bookings_unit        ON public.bookings (unit_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status      ON public.bookings (status);
CREATE INDEX IF NOT EXISTS idx_bookings_check_in    ON public.bookings (check_in);
CREATE INDEX IF NOT EXISTS idx_bookings_check_out   ON public.bookings (check_out);
CREATE INDEX IF NOT EXISTS idx_bookings_created_at  ON public.bookings (created_at);

-- Composite phục vụ overlap query:
--   new_check_in < check_out AND new_check_out > check_in
--   (WHERE unit_id = ? AND status IN ('pending','confirmed','checkin'))
CREATE INDEX IF NOT EXISTS idx_bookings_availability
    ON public.bookings (unit_id, status, check_in, check_out);

-- ═════════════════════════════════════════════════════════════════════════
-- REVIEWS / FAVORITES
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_reviews_unit      ON public.reviews (unit_id);
CREATE INDEX IF NOT EXISTS idx_reviews_customer  ON public.reviews (customer_id);
CREATE INDEX IF NOT EXISTS idx_reviews_status    ON public.reviews (status);

-- (customer_id, unit_id) đã có UNIQUE (tạo index tự động) — chỉ cần index unit
CREATE INDEX IF NOT EXISTS idx_favorites_unit    ON public.favorites (unit_id);

-- ═════════════════════════════════════════════════════════════════════════
-- RATE RULES — truy vấn giá theo unit + khoảng ngày
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_rate_rules_unit    ON public.rate_rules (unit_id);
CREATE INDEX IF NOT EXISTS idx_rate_rules_dates   ON public.rate_rules (start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_rate_rules_priority ON public.rate_rules (priority);

-- ═════════════════════════════════════════════════════════════════════════
-- AVAILABILITY BLOCKS — check chặn ngày theo unit + khoảng ngày
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_availability_blocks_unit_dates
    ON public.availability_blocks (unit_id, start_date, end_date);

-- ═════════════════════════════════════════════════════════════════════════
-- NEARBY PLACES / PROMOTIONS
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_nearby_places_property ON public.nearby_places (property_id);
CREATE INDEX IF NOT EXISTS idx_nearby_places_category ON public.nearby_places (category);

CREATE INDEX IF NOT EXISTS idx_promotions_active ON public.promotions (active);
CREATE INDEX IF NOT EXISTS idx_promotions_valid  ON public.promotions (valid_from, valid_until);

-- ═════════════════════════════════════════════════════════════════════════
-- BOOKING NIGHTS / PRICE ITEMS / PROMOTION USAGES
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_booking_nights_stay_date ON public.booking_nights (stay_date);
CREATE INDEX IF NOT EXISTS idx_booking_price_items_booking ON public.booking_price_items (booking_id, display_order);
CREATE INDEX IF NOT EXISTS idx_promotion_usages_promo_customer ON public.promotion_usages (promotion_id, customer_id);

-- ═════════════════════════════════════════════════════════════════════════
-- NOTIFICATIONS / CONTACT MESSAGES
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_notifications_customer ON public.notifications (customer_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_admin    ON public.notifications (admin_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created  ON public.notifications (created_at);

CREATE INDEX IF NOT EXISTS idx_contact_messages_status  ON public.contact_messages (status);
CREATE INDEX IF NOT EXISTS idx_contact_messages_created ON public.contact_messages (created_at);

-- ═════════════════════════════════════════════════════════════════════════
-- LOGS
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_login_logs_actor   ON public.login_logs (actor_type, actor_id);
CREATE INDEX IF NOT EXISTS idx_login_logs_created ON public.login_logs (created_at);

CREATE INDEX IF NOT EXISTS idx_audit_logs_entity  ON public.audit_logs (entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_admin   ON public.audit_logs (admin_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON public.audit_logs (created_at);

-- ═════════════════════════════════════════════════════════════════════════
-- JUNCTION TABLES — index chiều ngược (amenity → unit, tag → unit)
-- ═════════════════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_unit_amenities_amenity       ON public.unit_amenities (amenity_id);
CREATE INDEX IF NOT EXISTS idx_unit_experience_tags_tag     ON public.unit_experience_tags (tag_id);
