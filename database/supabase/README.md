# YangHomestay — Supabase/PostgreSQL

Database cho YangHomestay. Kiến trúc dữ liệu chính: **PROPERTY → UNIT → AVAILABILITY → BOOKING**. Booking luôn gắn với `unit_id`.

## 1. Migration order

| File | Mục đích | Thứ tự |
|---|---|---:|
| `001_schema.sql` | 24 application tables, constraints | 1 |
| `002_seed.sql` | Demo data; reset development data | 2 |
| `003_indexes.sql` | Secondary indexes | 3 |
| `004_rls.sql` | Enable RLS, không tạo public policies | 4 |
| `005_functions.sql` | `set_updated_at()`, `is_unit_available()` | 5 |
| `006_booking_rpc.sql` | Atomic booking RPC | 6 |
| `007_search_support.sql` | Rating view + availability search RPC | 7 |
| `008_real_homestay_images.sql` | Real-image mappings/data | 8 |
| `009_security_hardening.sql` | Khóa direct RPC grants + DB hardening | 9 |

`ERD.md` và `migration-notes.md` là tài liệu tham khảo.

## 2. Import

Fresh development database: chạy `001` → `009` theo đúng thứ tự trong Supabase SQL Editor.

> `002_seed.sql` có reset/TRUNCATE dữ liệu development. **Không chạy lại seed trên production có dữ liệu thật.**

Database đã tồn tại đến `008`: chỉ chạy `009_security_hardening.sql`.

Kiểm tra cơ bản:

```sql
select count(*) from public.admins;
select count(*) from public.properties;
select count(*) from public.units;
select count(*) from public.bookings;

select id, username, role, status
from public.admins
order by id;
```

## 3. Internal roles

Bảng `admins` dùng 3 role:

- `super_admin`
- `manager`
- `staff`

Password là bcrypt, tương thích `password_hash()` / `password_verify()` ở PHP. Không lưu plaintext password.

Demo seed:

```text
admin   / admin123   / super_admin
manager / manager123 / manager
staff   / staff123   / staff
```

Đổi demo passwords trước production.

## 4. RLS architecture

RLS được bật cho tất cả application tables và không tạo policy cho `anon`/`authenticated`. Application đi theo đường:

```text
Browser → PHP Session/RBAC → PHP backend → Supabase REST/RPC bằng server-side secret/service role
```

Do đó frontend không cần và không được nhận secret/service-role credential.

## 5. Migration 009 — security hardening

`009_security_hardening.sql` xử lý các điểm mà Supabase Security Advisor có thể cảnh báo khi `SECURITY DEFINER` functions/view nằm trong exposed schema:

- revoke `EXECUTE` từ `PUBLIC`, `anon`, `authenticated` cho:
  - `is_unit_available(...)`
  - `available_units(...)`
  - `create_booking_atomic(...)`
- grant lại `EXECUTE` cho `service_role`;
- `v_unit_rating` dùng `security_invoker = true`;
- `set_updated_at()` có fixed `search_path`.

Migration này không xóa dữ liệu, không thay đổi pricing formula, không thay đổi overlap rule và không rewrite `create_booking_atomic`.

## 6. Booking / availability invariants

```text
check_in  = inclusive
check_out = exclusive
```

Blocking booking states:

```text
pending | confirmed | checkin
```

Non-blocking:

```text
checkout | cancelled
```

Booking atomicity vẫn do `create_booking_atomic()` đảm nhiệm: DB transaction + advisory lock + overlap re-check + price snapshot.

## 7. Pricing snapshot

Mỗi đêm được khóa trong `booking_nights`; các dòng tiền hiển thị lưu ở `booking_price_items`.

```text
subtotal = SUM(booking_nights.nightly_rate)
total    = subtotal + cleaning_fee - discount_amount
nights   = check_out - check_in
```

Thay đổi rate rule tương lai không được làm đổi giá booking cũ.

## 8. Bảo mật credential

- Secret/service-role credential: backend only.
- `backend/config.supabase.local.php`: private + gitignored.
- Không ghi secret vào JavaScript, HTML, log hoặc repository public.
- Frontend chỉ gọi PHP APIs.

## 9. Verification after migration 009

Sau khi chạy 009, có thể xác minh grants:

```sql
select
  p.proname,
  has_function_privilege('anon', p.oid, 'EXECUTE') as anon_exec,
  has_function_privilege('authenticated', p.oid, 'EXECUTE') as authenticated_exec,
  has_function_privilege('service_role', p.oid, 'EXECUTE') as service_exec
from pg_proc p
join pg_namespace n on n.oid = p.pronamespace
where n.nspname = 'public'
  and p.proname in ('is_unit_available', 'available_units', 'create_booking_atomic')
order by p.proname;
```

Expected: `anon_exec = false`, `authenticated_exec = false`, `service_exec = true`.


## 10. Migration 010–013 — content/gallery integrity

- `010_content_image_refresh.sql`: đồng bộ bộ ảnh local/ảnh khu vực từ bản ImageFix trước.
- `011_gallery_capacity_logic_fix.sql`: rebuild gallery 16 unit và sửa các unit đang overclaim sức chứa so với số giường/ảnh minh họa.
- `012_production_polish.sql`: đồng bộ gallery canonical và sửa mã Unit 16 về `YH-CG-04`.
- `013_final_consistency_hardening.sql`: bước chốt không destructive: loại ảnh Unit 13 trùng asset unit khác, bỏ claim direct lake-view chưa đủ bằng chứng và sửa booking demo #8 vượt capacity.

`002_seed.sql` đã được đồng bộ lại với **16 unit + 61 gallery rows** của LogicFix để reset môi trường development cho ra dữ liệu hợp lý. Với database đang chạy/production, **không chạy lại `002_seed.sql`**. Chạy 010 nếu chưa từng áp dụng, sau đó 011, 012 và 013. Các migration gallery chỉ rebuild `unit_images` cho unit 1–16; không xóa booking, user, payment hoặc dữ liệu auth.

Các capacity được siết về logic sức chứa: unit 2/8/11/14 → 2 khách; unit 6/9 → 4 khách; unit 12 → 3 khách. Unit 14 có gallery 6 ảnh cùng vibe, bedroom thấy rõ giường + bàn làm việc và capacity 2 khách/1 giường, vì vậy không còn quảng bá 3 khách.

---

Xem root `README.md` để biết portal URLs, RBAC matrix, XAMPP và HelioHost deployment.
