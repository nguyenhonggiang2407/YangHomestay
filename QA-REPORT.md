# YangHomestay — QA REPORT FINAL

Ngày: **17/08/2026**

## 1. Phạm vi thực tế đã test

### Static/source QA
- 21 HTML pages.
- 48 PHP files.
- 8 external JS files.
- 15 inline JavaScript blocks.
- 115 text/source/config files trong asset/secret scan.
- Supabase canonical seed + MySQL canonical seed.
- 16/16 unit records.
- 61 active unit-gallery rows.
- 15 property-image rows.

### Automated business-logic tests có sẵn trong project
- Pricing: **23 pass / 0 fail**.
- Availability/date-overlap: **16 pass / 0 fail**.
- RBAC matrix: **14 pass / 0 fail**.

## 2. Syntax / runtime-static checks

| Check | Kết quả |
|---|---:|
| PHP syntax | 48/48 PASS |
| External JS syntax | 8/8 PASS |
| Inline JS syntax | 15/15 PASS |
| HTML duplicate IDs | 0 issue |
| `href="#"` | 0 |
| `<img>` thiếu `alt` | 0 |
| Form control thiếu label/accessibility name trong static audit | 0 |
| Local HTML href/src checked | 138, missing 0 |
| Asset references trong source checked | 756, missing 0 |
| Obvious secret pattern trong main tree | 0 hit |
| `backend/config.supabase.local.php` trong main tree | Không có |
| `.gitignore` ignore private config | PASS |
| `.htaccess` có rule bảo vệ config/sensitive files | PASS |

## 3. Homestay data/gallery QA

### Unit dataset
- **16/16** unit parse được từ Supabase seed.
- unit code unique: PASS.
- slug unique: PASS.
- heuristic capacity/bed/bedroom/bathroom: **0 issue**.

### Gallery evidence
Rule QA:
- mỗi unit có cover;
- có bedroom/bed evidence;
- có bathroom evidence;
- unit 3+ khách phải có bằng chứng nhiều sleeping surfaces/phòng ngủ hoặc sofa-bed.

Kết quả: **0 issue / 16 unit**.

Các case trọng yếu:
- Unit 6: 4 khách / 3 giường / 2 phòng ngủ → bedroom + second-bedroom.
- Unit 9: 4 khách / 3 giường / 2 phòng ngủ → bedroom + second-bedroom; ảnh Hồ Tây ghi là ảnh khu vực.
- Unit 12: 3 khách / 2 giường → bedroom + sofa-bed.
- Unit 14: 2 khách / 1 giường → gallery 6 ảnh gồm bedroom + workspace + bathroom + kitchen/living.
- Unit 16: 4 khách / 3 giường / 2 phòng ngủ → bedroom + second-bedroom.

### Duplicate image audit trên **active canonical unit gallery**
- Active gallery rows: **61**.
- Missing local image: **0**.
- SHA-256 exact duplicate groups: **0**.
- dHash near-duplicate (distance ≤ 6): **0**.

Lưu ý: project vẫn giữ một số asset lịch sử không còn active để các migration cũ và IMAGE_SOURCES còn truy vết được. Tiêu chí duplicate ở trên áp dụng cho **gallery active cuối**, không phải mọi file nằm trong thư mục assets.

### Supabase ↔ MySQL seed parity
- Units: **16 vs 16 — MATCH**.
- Unit images: **61 vs 61 — MATCH**.
- Property images: **15 vs 15 — MATCH**.

## 4. Booking / availability / concurrency

Đã kiểm tra bằng unit tests và source audit:
- check-in inclusive / check-out exclusive.
- invalid date / checkout <= checkin / past check-in validation.
- pending/confirmed/checkin block availability; checkout/cancelled không block.
- pricing nights/subtotal/cleaning/discount/total unit tests PASS.
- RPC `create_booking_atomic` vẫn dùng transaction + advisory lock + capacity/overlap validation phía database.
- Customer cancel có optimistic concurrency guard.
- Admin status/payment update có optimistic concurrency guard.
- Demo booking #8 đã được sửa về 2 khách để không vượt max capacity Unit 2.

**Chưa tuyên bố live booking E2E PASS** vì môi trường audit không có quyền chạy production Supabase E2E với credential qua network.

## 5. Auth / RBAC / security

Đã kiểm tra:
- RBAC automated test: **14/14 PASS**.
- customer protected request revalidate account phía server.
- internal auth datasource outage không bị hiểu nhầm thành logout.
- login/register CSRF vẫn tồn tại.
- main distributable không chứa private Supabase credential.
- frontend source không chứa Supabase service-role/secret.
- source scan không phát hiện obvious JWT/service-role/OpenAI-style secret literal.

**Known security limitation / kiến trúc:**
- Backend dùng server-side Supabase credential để gọi Data API. File này phải tồn tại trên HelioHost nhưng không được commit GitHub/public ZIP.
- Demo passwords trong development seed chỉ dành cho demo; phải đổi trước khi dùng như hệ thống thật.

## 6. Error/loading/empty states

Đã sửa và audit:
- thiếu Supabase config: HTTP 503 + message thân thiện, không còn `Server configuration error` ở response mới.
- listing/detail/YangMatch/auth/booking critical datasource error không giả thành empty/404/login fail.
- toast duplicate được suppress trong khoảng ngắn.
- auth check 5xx không cache trạng thái logout giả.
- dynamic images có fallback local ở customer flows chính.

Local CLI smoke test khi **cố ý không đặt private config**:
- `GET backend/customer-api.php?action=units` → **HTTP 503**.
- JSON message → `Dịch vụ dữ liệu tạm thời chưa sẵn sàng. Vui lòng thử lại sau.`

Đây là expected fail-safe, không phải kiểm tra kết nối Supabase thật.

## 7. Card / experience mapping QA

Source audit xác nhận:
- Workcation → `homestays.html?tag=workcation`.
- Hai người → `tag=couple`.
- Bình yên → `tag=quiet`.
- Phố Cổ → district Hoàn Kiếm + `tag=old-quarter`.
- View thoáng → `tag=lake-view`.
- **Ẩm thực Hà Nội** → `experience-detail.html?slug=hanoi-food`, không map vào unit detail.
- Experience detail dùng local food assets và có fallback.

## 8. SEO / entry point

Source audit:
- root `index.php` redirect về `/customer/`.
- `.htaccess` canonicalize explicit `/customer/index.html` → `/customer/`.
- public pages có title/meta description/canonical trong static audit.
- private/account/booking pages được `noindex,nofollow`.
- `robots.txt` và `sitemap.xml` tồn tại.

**Không xác nhận được redirect trực tiếp trên production trong pass này** vì web-fetch tool trả cache miss và container không resolve được domain. Xem Known limitations.

## 9. Responsive/browser QA — giới hạn bắt buộc phải nói thật

Đã **thử** chạy Chromium/Playwright ở các width:
- 360
- 390
- 430
- 768
- 1024
- 1366
- 1440

Trên các page representative:
- homepage;
- homestays/workcation;
- unit detail id=14;
- login;
- YangMatch;
- profile.

Kết quả: Chromium của môi trường audit chặn cả localhost và `file://` với `ERR_BLOCKED_BY_ADMINISTRATOR`. Vì vậy **không có cơ sở để tuyên bố visual responsive browser test PASS** cho 42 case này.

Phần responsive đã được static audit qua CSS/HTML structure, nhưng sau deploy cần smoke-test bằng Chrome thật ở các breakpoint trên.

## 10. Production website access — giới hạn

Đã thử truy cập `https://yanghomestay.helioho.st/` bằng web tool, nhưng fetch trả `Cache miss`; container curl cũng không resolve được hostname. Do đó pass này **không tuyên bố production E2E đã chạy**.

Hai screenshot production do người dùng cung cấp được dùng như bằng chứng UX cho lỗi trước deploy:
- listing Workcation báo `Server configuration error`;
- login hiện hai toast `Server configuration error`.

Source đã sửa root cause/microcopy và tạo gói deploy private có config, nhưng production chỉ thay đổi sau khi deploy package mới.

## 11. Known limitations còn lại

1. Chưa có browser visual regression hợp lệ trong môi trường hiện tại do policy Chromium.
2. Chưa chạy live Supabase booking/auth E2E từ môi trường audit do network/runtime constraint; PHP CLI tại đây cũng không có cURL extension.
3. Hệ thống chưa có payment gateway thật; `payment_status` là workflow nội bộ/demo. Không claim thanh toán online production.
4. Một số ảnh vẫn là ảnh stock hợp pháp được curate; gallery logic đã được normalize nhưng chưa thể chứng minh đây là ảnh chụp từ property thương mại thật ngoài đời.
5. Để dùng tiền thật, cần vận hành thực tế: host/property verification, policy pháp lý, support SLA, privacy/terms, production monitoring/backups.

## 12. Scorecard trung thực sau pass này

### Góc nhìn người dùng thật

| Hạng mục | Điểm /10 |
|---|---:|
| First impression | 8.8 |
| Trust | 8.3 |
| Navigation | 8.8 |
| Search | 8.5 |
| Filters | 8.5 |
| Unit Detail | 8.8 |
| Images | 8.5 |
| Data credibility | 8.8 |
| Booking logic | 8.7 |
| Reviews | 8.0 |
| YangMatch | 9.0 |
| Mobile | 8.0* |
| Content | 8.8 |
| **Overall** | **8.6** |

`*` Mobile là provisional vì không thể hoàn tất browser visual test trong môi trường audit.

**Nếu là tiền thật của tôi:** chưa chọn “đặt và thanh toán ngay” như một OTA thương mại hoàn chỉnh vì project chưa có payment/operations/legal verification live và production E2E chưa được chứng minh trong pass này. Nhưng với tư cách website portfolio/đồ án có booking-request workflow, mức trust và logic đã đủ thuyết phục để tiến hành bước kiểm tra availability/đăng nhập và gửi yêu cầu đặt.

### Góc nhìn giảng viên CNTT

| Hạng mục | Điểm /10 |
|---|---:|
| Idea | 9.0 |
| Problem/Solution | 8.8 |
| UI | 9.0 |
| UX | 8.7 |
| Frontend | 8.7 |
| Backend | 8.9 |
| Database | 9.0 |
| API | 8.8 |
| Authentication | 8.8 |
| RBAC | 9.0 |
| Business logic | 9.1 |
| Data consistency | 9.2 |
| Security | 8.7 |
| Accessibility | 8.4 |
| Performance | 8.4 |
| SEO | 8.5 |
| Code quality | 8.7 |
| Maintainability | 8.8 |
| Deployment readiness | 8.6 |
| Portfolio value | 9.2 |
| Defense readiness | 9.0 |
| **Overall** | **8.9** |

Các câu hỏi hội đồng vẫn có thể hỏi khó:
- Vì sao chọn PHP server-side proxy + Supabase Data API thay vì client SDK/RLS trực tiếp?
- `create_booking_atomic` ngăn double booking như thế nào khi có 2 request đồng thời?
- Tại sao service-role credential phải nằm server-side và cách rotate/revoke ra sao?
- Phân biệt authentication, authorization và RBAC trong project.
- Vì sao customer cancel/admin status dùng optimistic concurrency?
- YangMatch hard constraints và weighted score hoạt động thế nào?
- Vì sao không coi ảnh stock/seed review là dữ liệu thương mại thật?
- Nếu scale lên nhiều property/booking thì monitoring, cache, queue/payment/webhook sẽ thiết kế thế nào?

## 13. Trước → sau

| Hạng mục | Trước pass audit tổng thể | Sau |
|---|---:|---:|
| Người dùng thật | 7.7/10 | **8.6/10** |
| Giảng viên CNTT | 7.8/10 | **8.9/10** |
| Trust | 7.2/10 | **8.3/10** |
| UI/UX | 8.3/10 | **8.8/10** |
| Data consistency | 7.0/10 | **9.2/10** |
| Production readiness | 7.1/10 | **8.6/10** |

Không chấm 10/10 vì các limitation ở mục 11 vẫn là thật.
