# YangHomestay — CHANGELOG FINAL

Ngày audit/finalize: **17/08/2026**  
Mục tiêu: polish + fix + normalize + make believable + production-ready, giữ nguyên brand/visual direction/YangMatch.

## 1. P0 — Backend configuration & production error handling

### Lỗi phát hiện
- Khi `backend/config.supabase.local.php` hoặc biến môi trường Supabase bị thiếu, backend từng trả chuỗi kỹ thuật **`Server configuration error`**. Ảnh production do người dùng cung cấp cho thấy lỗi này xuất hiện ở listing và login.
- Một số service có thể biến lỗi datasource thành `404`, `401`, danh sách rỗng hoặc dữ liệu thiếu, làm người dùng hiểu sai bản chất sự cố.

### Đã sửa
- `backend/bootstrap.php`
  - thiếu Supabase config → HTTP **503** với microcopy: `Dịch vụ dữ liệu tạm thời chưa sẵn sàng. Vui lòng thử lại sau.`;
  - chi tiết kỹ thuật chỉ ghi server log, không lộ path/credential.
- `backend/lib/SupabaseClient.php`
  - cập nhật contract/comment đúng hành vi 503 mới.
- `backend/services/AuthService.php`
  - login/register/profile/change-password phân biệt lỗi datasource với sai credential/404;
  - `requireCustomer()` revalidate customer qua database cho request protected, tránh session cũ tiếp tục có quyền khi account bị disable/delete;
  - transient datasource error không bị biến thành logout giả.
- `backend/services/AdminAuthService.php`
  - transient datasource error không bị hiểu nhầm là phiên đăng nhập hết hạn.
- `backend/services/UnitService.php`, `backend/services/PropertyService.php`
  - critical source fail → 503 thay vì trả list/detail thiếu hoặc empty giả;
  - unknown tag → empty result hợp lệ, không silently bỏ filter rồi trả toàn bộ unit.
- `backend/services/BookingService.php`
  - my-bookings/detail/cancel và bước enrich unit/property/cover fail closed khi datasource lỗi;
  - không trả booking list nhìn như “mất dữ liệu” khi API phụ đang lỗi.
- `backend/services/AdminBookingService.php`
  - detail/status/payment lookup phân biệt 503 với 404;
  - booking nights/price-items lỗi không còn bị render thành mảng rỗng giả.
- `backend/services/ReviewService.php`
  - admin review list/lookup/moderation phân biệt lỗi datasource;
  - rejected review không ghi `approved_at`.

## 2. P0 — Booking business logic / race condition

- Booking mới vẫn đi qua RPC `create_booking_atomic`, không trust giá/client-side availability.
- Customer cancel:
  - update dùng điều kiện `id + customer_id + current_status`;
  - nếu nhân viên vừa check-in/check-out ở request khác → trả **409**, không ghi đè trạng thái mới.
- Admin/manager booking status & payment status:
  - thêm optimistic-concurrency guard theo trạng thái vừa đọc;
  - concurrent update → 409 + yêu cầu reload.
- Booking UI đã dùng microcopy đúng bản chất **“Gửi yêu cầu đặt” / “Yêu cầu đặt chỗ đã được tạo”**, không giả vờ đã thanh toán/xác nhận tức thì.
- Availability/quote lỗi backend được xử lý fail-closed; không tự bật lại CTA dựa trên quote cũ.

## 3. P0 — Data consistency của 16 unit

Đã audit toàn bộ **16/16 unit** theo `max_guests`, bedrooms, beds, bathrooms, area, price, description, tags, amenities và gallery.

### Unit 14 — Cau Giay Business Apartment
- giữ cấu hình đã normalize: **2 khách · 1 phòng ngủ · 1 giường · 1 phòng tắm · 42m²**;
- gallery **6 ảnh**: living/kitchen overview, bedroom, kitchen, bathroom, living room, workspace;
- không còn logic cũ “3 khách nhưng không có bằng chứng chỗ ngủ thứ ba”.

### Các sửa consistency cuối
- **Unit 16**: code sai kiểu dữ liệu/path ở seed cũ → `YH-CG-04`.
- **Unit 13 — Cau Giay Modern Studio**:
  - loại ảnh phụ active bị trùng chính xác asset của unit khác;
  - gallery active còn 3 ảnh đủ chức năng: living overview + bedroom có quầy bar/counter + bathroom;
  - mô tả đổi thành “quầy bar nhỏ có thể dùng làm bàn làm việc”, không claim một workspace riêng không có ảnh bằng chứng.
- **Unit 9 — West Lake Family Apartment**:
  - bỏ `lake_view` amenity/tag khi gallery căn không chứng minh direct lake view;
  - ảnh Hồ Tây được ghi rõ là **ảnh khu vực lưu trú**, không giả là view từ căn;
  - copy đổi từ “view hồ” sang “gần Hồ Tây”.
- **Unit 5 — Truc Bach Balcony Room**:
  - bỏ direct lake-view tag/claim chưa đủ bằng chứng;
  - copy thành “ban công thoáng gần hồ Trúc Bạch”.
- **Booking demo #8**:
  - trước: 3 khách trong Unit 2 max 2;
  - sau: 2 người lớn, 0 trẻ em, tổng 2 khách; giữ nguyên lịch/giá/trạng thái.

## 4. P0/P1 — Gallery & image mapping

- Canonical fresh seed: **16 units / 61 active unit-gallery rows / 15 property images**.
- 61 active gallery paths đều tồn tại local.
- SHA-256 audit active gallery: **0 exact duplicate giữa các row**.
- dHash audit active gallery: **0 near-duplicate ≤ 6**.
- Không hotlink ảnh accommodation runtime; ảnh active dùng local assets.
- “Ẩm thực Hà Nội” vẫn map đúng:
  - `experience-detail.html?slug=hanoi-food`;
  - ảnh food local;
  - 6 món + gợi ý Phố Cổ/Ba Đình/Hồ Tây.
- Workcation/Hai người/Bình yên/Phố Cổ/View thoáng tiếp tục map theo đúng tag/link hiện có.

## 5. P1 — YangMatch

`backend/services/YangMatchService.php`:
- thêm hard capacity constraints theo companion trước khi scoring;
- `budget < 0` → 422;
- giới hạn số result hợp lệ;
- datasource critical failure → 503 thay vì “không có gợi ý” giả;
- scoring vẫn deterministic theo budget/style/amenities/companion/location;
- trả thêm `minimum_guests` để logic dễ giải thích khi bảo vệ.

UI tiếp tục dùng tiếng Việt: Một mình / Cặp đôi / Nhóm bạn / Gia đình / Công tác.

## 6. P1 — Auth/RBAC/Security

- Customer protected API revalidate trạng thái user phía server.
- Internal role guard vẫn là server-side (`backend/portal.php` + session + RBAC), không chỉ hide menu.
- Login/register giữ CSRF; login thêm guard chống double-submit.
- Main project **không chứa `backend/config.supabase.local.php`** và secret scan final = 0 hit.
- `.gitignore` giữ private config ngoài Git.
- `.htaccess` chặn config, SQL, logs, diagnostics/tests và internal source nhạy cảm.
- Frontend không chứa Supabase secret/service-role key.

## 7. P1/P2 — UX/error/accessibility/SEO

- `customer/js/ui.js`: dedupe toast cùng type/message trong 1.4s, xử lý trường hợp ảnh production từng hiện hai toast giống nhau.
- `checkAuth()` không cache `authenticated=false` khi lỗi 5xx, tránh biến outage thành logout giả.
- Private pages thêm `noindex,nofollow`: login, register, profile, bookings, booking detail/success, favorites, review.
- Heading hierarchy được normalize ở filter/profile/property detail/YangMatch.
- Static accessibility audit final:
  - 0 duplicate HTML id;
  - 0 `<img>` thiếu `alt`;
  - 0 control quan trọng thiếu label/accessibility name;
  - 0 `href="#"` dead link.
- Public pages có title/meta description/canonical theo audit tĩnh.

## 8. Database migrations mới

### Supabase
- `database/supabase/013_final_consistency_hardening.sql`
  - không TRUNCATE;
  - loại duplicate active của Unit 13;
  - normalize U5/U9 lake-view claim;
  - sửa booking demo #8 vượt capacity.

### MySQL tương đương
- `database/013_final_consistency_hardening_mysql.sql`

Fresh seeds cũng đã đồng bộ cùng trạng thái canonical:
- `database/supabase/002_seed.sql`
- `database/yang_homestay_seed.sql`

## 9. Các file chính thay đổi trong pass cuối

- `backend/bootstrap.php`
- `backend/lib/SupabaseClient.php`
- `backend/services/AuthService.php`
- `backend/services/AdminAuthService.php`
- `backend/services/UnitService.php`
- `backend/services/PropertyService.php`
- `backend/services/BookingService.php`
- `backend/services/AdminBookingService.php`
- `backend/services/ReviewService.php`
- `backend/services/YangMatchService.php`
- `backend/customer-api.php`
- `customer/js/ui.js`
- `customer/login.html`
- `customer/homestays.html`
- `customer/profile.html`
- `customer/property-detail.html`
- `customer/yangmatch.html`
- private customer pages (robots noindex)
- `database/supabase/002_seed.sql`
- `database/yang_homestay_seed.sql`
- `database/supabase/012_production_polish.sql`
- `database/012_production_polish_mysql.sql`
- `database/supabase/013_final_consistency_hardening.sql`
- `database/013_final_consistency_hardening_mysql.sql`
- database/root README + deployment/QA docs.

## 10. Không thay đổi có chủ ý

- Không redesign brand/hero/theme.
- Không xóa YangMatch.
- Không tạo payment gateway giả.
- Không fake review/statistics lớn.
- Không chuyển business-critical booking logic sang frontend.
- Không reset production users/bookings/payments trong migration 013.
