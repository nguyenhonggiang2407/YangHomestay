# YangHomestay

> Modern Hanoi Boutique Homestay booking platform — customer booking, YangMatch, Supabase-backed API and RBAC portals.

## Production UI v4
Bản này giữ kiến trúc backend/database hiện tại và nâng cấp customer experience: hero, navigation, search, homestay cards, district discovery, YangMatch wizard presentation, footer và responsive. Root `index.php` tương thích HelioHost và chuyển vào `customer/`.


**Ở đúng gu, sống đúng chất Hà Nội.**

YangHomestay là website đặt homestay tại Hà Nội với 4 portal tách biệt, dùng HTML/CSS/Vanilla JavaScript, PHP Session và Supabase PostgreSQL qua REST/RPC.

```text
Browser (Customer / Super Admin / Manager / Staff)
        ↓ HTTPS + PHP Session + CSRF
PHP Backend (RBAC + business services)
        ↓ HTTPS :443
Supabase PostgreSQL (REST API / RPC)
```

## 1. Tech stack

- Frontend: HTML5, CSS3, Vanilla JavaScript.
- Backend: PHP 8+, PHP Session, cURL/HTTPS.
- Database: Supabase PostgreSQL.
- Auth: `password_hash()` / `password_verify()` + PHP Session; không dùng Supabase Auth cho user của ứng dụng.
- Production target: Apache/PHP shared hosting, đặc biệt HelioHost/Plesk.
- Không yêu cầu Node runtime, React, Next.js, Docker, Redis hoặc Composer.

## 2. Portal URLs

| Portal | URL | Role được phép |
|---|---|---|
| Customer | `/customer/` | customer |
| Super Admin | `/admin/login.html` → `/admin/index.html` | `super_admin` |
| Manager | `/manager/login.html` → `/manager/index.html` | `manager` (và `super_admin` nếu mở trực tiếp) |
| Staff / Lễ tân | `/staff/login.html` → `/staff/index.html` | `staff`, `manager`, `super_admin` |

`/admin/index.html`, `/manager/index.html`, `/staff/index.html` được Apache rewrite qua `backend/portal.php` để kiểm tra PHP Session + role **trước khi trả HTML dashboard**. Frontend guard vẫn tồn tại như lớp phòng thủ bổ sung, nhưng không phải nguồn phân quyền chính.

## 3. Demo accounts

| Portal | Username | Password | Role |
|---|---|---|---|
| Super Admin | `admin` | `admin123` | `super_admin` |
| Manager | `manager` | `manager123` | `manager` |
| Staff | `staff` | `staff123` | `staff` |

Customer demo dùng các account trong seed, mật khẩu demo `customer123`.

> **Quan trọng:** `admin123`, `manager123`, `staff123` và `customer123` chỉ dành cho development/demo. Đổi toàn bộ demo password trước khi public production.

## 4. Permission matrix

Backend policy nằm tại `backend/lib/InternalAccess.php`.

| Feature | Super Admin | Manager | Staff |
|---|:---:|:---:|:---:|
| Dashboard | YES | YES | YES |
| Bookings / booking detail | YES | YES | YES |
| Reservation calendar | YES | YES | YES |
| Check-in / Check-out | YES | YES | YES |
| Customers | YES | YES | LIMITED VIEW |
| Properties | CRUD | CRUD | VIEW |
| Units | CRUD | CRUD | VIEW |
| Pricing / Rate rules | YES | YES | NO |
| Promotions | YES | YES | NO |
| Reviews | MODERATE | MODERATE | VIEW |
| Revenue | YES | YES | NO |
| Internal accounts | YES | NO | NO |
| Roles | YES | NO | NO |
| Audit logs | YES | VIEW | NO |
| System settings | YES | NO | NO |

Các request nhạy cảm không dựa vào hidden menu, localStorage hoặc JavaScript role. `backend/api.php` gọi RBAC server-side cho từng action; request sai quyền trả `403`.

## 5. Cấu trúc chính

```text
WEBODER/
├── .htaccess                         # Portal rewrite + chặn private config/log
├── customer/                         # Customer portal hiện hữu
├── admin/                            # Super Admin portal
│   ├── login.html
│   ├── index.html
│   ├── css/admin.css
│   └── js/
├── manager/                          # Manager portal
│   ├── login.html
│   └── index.html
├── staff/                            # Lễ tân portal
│   ├── login.html
│   ├── index.html
│   └── js/staff.js
├── assets/css/internal-portals.css   # Shared Manager/Staff UI
├── backend/
│   ├── api.php                       # Internal API router + RBAC
│   ├── customer-api.php              # Customer API router
│   ├── portal.php                    # Server-side portal guard
│   ├── bootstrap.php
│   ├── config.supabase.example.php
│   ├── config.supabase.local.php     # Private, GITIGNORED
│   ├── lib/
│   │   ├── InternalAccess.php        # Role/permission policy
│   │   └── SupabaseClient.php
│   ├── services/
│   │   ├── AdminAuthService.php
│   │   ├── InternalAdminService.php
│   │   └── ...
│   └── tests/
│       ├── test-pricing.php
│       ├── test-availability.php
│       └── test-rbac.php
└── database/supabase/
    ├── 001_schema.sql ... 008_real_homestay_images.sql
    └── 009_security_hardening.sql
```

## 6. Authentication flow

### Internal accounts

1. Login page gửi username/password + portal đang đăng nhập tới `backend/api.php?action=login`.
2. Backend xác thực bcrypt từ bảng `admins`.
3. Backend kiểm tra role có được phép đăng nhập portal đó không.
4. Sau login hợp lệ, session ID được regenerate và session lưu `admin_id`, `admin_role`, `admin_name`.
5. Dashboard và mọi internal API action tiếp tục xác minh session/role server-side.
6. Logout xóa session internal đúng cách.

Cross-role examples:

- `staff` đăng nhập tại `/admin/login.html` → `403`, không tạo Super Admin session.
- `manager` tự mở `/admin/index.html` → bị portal guard chuyển về Manager portal.
- anonymous tự mở `/staff/index.html` → chuyển về `/staff/login.html`.
- staff gọi API pricing/admin-account trực tiếp → `403`.

Customer và internal account là hai luồng riêng: customer dùng bảng `customers`; internal portals dùng bảng `admins`.

## 7. API client / lỗi login cũ

Internal portals dùng chung `admin/js/admin-api.js`.

Load order bắt buộc tại login:

```text
admin-config.js → admin-api.js → admin-login.js
```

API client có `get()`, `post()`, `patch()`, `delete()`, CSRF handling và timeout. Login dùng `try/catch/finally` tương đương qua Promise chain để luôn khôi phục trạng thái button khi request lỗi; không còn phụ thuộc vào `window.ADMIN_CONFIG.API` trước khi config được khởi tạo.

## 8. Supabase config

Giữ kiến trúc:

```text
Browser → PHP backend → HTTPS → Supabase
```

Secret chỉ được dùng server-side.

1. Copy `backend/config.supabase.example.php` thành `backend/config.supabase.local.php` nếu chưa có.
2. Điền URL và secret/service-role credential trong file local.
3. `backend/config.supabase.local.php` đã nằm trong `.gitignore` và `.htaccess` chặn truy cập trực tiếp.
4. Không đưa secret vào HTML/JS, log hoặc repository public.

Bản ZIP bàn giao **không chứa** `backend/config.supabase.local.php` hay secret production. Tạo file local hoặc biến môi trường trực tiếp trên server sau khi upload; không commit file đó lên GitHub.

## 9. Database migrations

Fresh database chạy theo thứ tự:

```text
001_schema.sql
002_seed.sql                 # development only; có TRUNCATE
003_indexes.sql
004_rls.sql
005_functions.sql
006_booking_rpc.sql
007_search_support.sql
008_real_homestay_images.sql
009_security_hardening.sql
010_content_image_refresh.sql
011_gallery_capacity_logic_fix.sql
012_production_polish.sql       # production data polish / canonical gallery + unit-code repair
013_final_consistency_hardening.sql # final data/claim/image consistency repair
```

`009_security_hardening.sql` không sửa logic booking/pricing và không xóa dữ liệu. Nó:

- revoke quyền gọi trực tiếp các `SECURITY DEFINER` RPC từ `anon`/`authenticated`;
- chỉ grant RPC đó cho `service_role`;
- đặt `v_unit_rating` thành `security_invoker`;
- cố định `search_path` của `set_updated_at()`.

Với database hiện có: **không chạy lại `002_seed.sql` trên production**. Nếu chưa áp dụng các migration mới, chạy các migration còn thiếu theo thứ tự `009_security_hardening.sql` → `010_content_image_refresh.sql` → `011_gallery_capacity_logic_fix.sql` → `012_production_polish.sql` → `013_final_consistency_hardening.sql`. Migration 013 là bước chốt của bản FINAL: loại mapping ảnh còn trùng, chuẩn hóa claim hình ảnh và sửa snapshot booking demo vượt capacity mà không reset user/booking/payment.

## 10. Booking engine

Các phần cốt lõi vẫn giữ nguyên:

- availability convention: check-in inclusive / check-out exclusive;
- overlap protection;
- `create_booking_atomic`;
- advisory-lock based conflict protection;
- booking nights + pricing snapshot;
- promotion calculation;
- booking state machine;
- check-in/check-out rules.

Portal/RBAC changes không chuyển logic booking sang frontend và không trust giá từ client.

## 11. Local setup — XAMPP

1. Đặt toàn bộ folder `WEBODER` vào Apache document root, ví dụ `C:\xampp\htdocs\ks\WEBODER`.
2. Bật Apache `mod_rewrite` và cho phép `.htaccess` (`AllowOverride All`) để server-side portal guard hoạt động.
3. Bảo đảm PHP có extension cURL.
4. Cấu hình `backend/config.supabase.local.php`.
5. Mở:

```text
http://localhost/ks/WEBODER/customer/
http://localhost/ks/WEBODER/admin/login.html
http://localhost/ks/WEBODER/manager/login.html
http://localhost/ks/WEBODER/staff/login.html
```

API/base path được tính tương đối, không phụ thuộc `/ks/WEBODER/` trong application runtime.

## 12. HelioHost deployment

Upload **toàn bộ nội dung folder `WEBODER/`** vào document root của domain/subdomain (thường là folder website trong Plesk), sao cho `.htaccess`, `customer/`, `admin/`, `manager/`, `staff/`, `backend/`, `assets/` nằm cùng cấp.

Checklist:

1. PHP 8+ và cURL enabled.
2. Apache rewrite / `.htaccess` được áp dụng.
3. HTTPS bật.
4. `backend/config.supabase.local.php` có credential server-side đúng.
5. Chạy các migration còn thiếu theo thứ tự `009_security_hardening.sql` → `010_content_image_refresh.sql` → `011_gallery_capacity_logic_fix.sql` → `012_production_polish.sql` → `013_final_consistency_hardening.sql`.
6. Test `customer/unit-detail.html?id=14`: tối đa 2 khách, gallery có phòng khách/bếp, phòng ngủ, workspace, bếp, WC và góc sinh hoạt.
7. Test cả 4 login URLs, refresh session, logout và cross-role direct URL/API.
8. Đổi demo passwords trước production.

Không cần Node runtime, Docker runtime, Redis hay background process.

## 13. Security notes

- Server-side RBAC: `InternalAccess.php` + role từ PHP Session được đồng bộ lại với database.
- Server-side portal guard: `backend/portal.php` + `.htaccess`.
- State-changing internal requests dùng CSRF.
- Session regenerate sau login; cookie security được cấu hình trong bootstrap/session layer.
- Admin password chỉ lưu bcrypt.
- Supabase secret không xuất hiện ở frontend.
- API client có timeout và friendly errors; raw Supabase/database internals không được trả trực tiếp cho production UI.
- Internal account management chỉ `super_admin`.
- Manager/Staff không thể tự nâng quyền bằng request trực tiếp.

## 14. Tests

```bash
php backend/tests/test-pricing.php
php backend/tests/test-availability.php
php backend/tests/test-rbac.php
```

Syntax audit:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check admin/js/admin-api.js
node --check admin/js/admin-login.js
node --check admin/js/admin.js
node --check staff/js/staff.js
```

`backend/tests/live-test.php` cần PHP cURL để gọi Supabase thật.

## 15. Production checklist

- [ ] Đổi `admin123`, `manager123`, `staff123` và các customer demo password.
- [ ] Chạy `009_security_hardening.sql` nếu database hiện tại chưa được harden.
- [ ] Xác nhận PHP cURL.
- [ ] Xác nhận Apache rewrite + `.htaccess`.
- [ ] Test Super Admin, Manager, Staff, Customer.
- [ ] Test direct URL và direct API bằng role thấp hơn.
- [ ] Không public `config.supabase.local.php`.
- [ ] Không commit logs/credentials.

---

© 2026 YangHomestay · Ở đúng gu, sống đúng chất Hà Nội.