# YangHomestay — DEPLOYMENT HELIOHOST

Bản final: **17/08/2026**

## 1. Có 2 package — dùng đúng mục đích

### `YangHomestay-FINAL.zip`
- GitHub/portfolio-safe.
- **Không chứa** `backend/config.supabase.local.php`.
- Dùng để lưu source, nộp đồ án, đưa GitHub.

### `YangHomestay-HELIOHOST-DEPLOY.zip`
- Bản private dành cho chính HelioHost của bạn.
- Có `backend/config.supabase.local.php` được lấy từ hotfix bạn đã cung cấp.
- **Không upload ZIP này lên GitHub, Drive public hoặc repository public.**

## 2. Document root

Với cấu trúc deploy hiện tại, nội dung project phải nằm **trực tiếp trong document root** của domain, ví dụ `httpdocs/`:

```text
httpdocs/
├── .htaccess
├── index.php
├── customer/
├── admin/
├── manager/
├── staff/
├── backend/
├── assets/
├── robots.txt
└── sitemap.xml
```

Không để thành:

```text
httpdocs/YangHomestay-FINAL/customer/...
```

trừ khi domain document root đã được trỏ vào chính folder đó.

## 3. Cách deploy khuyến nghị để sửa lỗi `Server configuration error`

1. Backup toàn bộ `httpdocs` hiện tại.
2. Backup riêng `backend/config.supabase.local.php` nếu đang tồn tại.
3. Upload **`YangHomestay-HELIOHOST-DEPLOY.zip`** vào `httpdocs`.
4. Extract **ngay trong `httpdocs`** và cho phép overwrite source cũ.
5. Kiểm tra hidden file `.htaccess` vẫn tồn tại sau extract.
6. Kiểm tra file private tồn tại:
   - `backend/config.supabase.local.php`
7. Không mở/nội dung credential trên trình duyệt, không paste lên chat/repository public.
8. Nếu hosting cho chỉnh permission, ưu tiên:
   - directories: 755;
   - normal files: 644;
   - private config: 600 hoặc 640 **nếu PHP vẫn đọc được**. Nếu gây 500 permission thì trả về permission hosting mặc định.

## 4. Supabase migration

### Database hiện tại đã chạy tới 012
Chỉ chạy:

```text
database/supabase/013_final_consistency_hardening.sql
```

Migration 013:
- không TRUNCATE;
- không xóa user/booking/payment history;
- loại image mapping Unit 13 còn trùng;
- normalize lake-view claim Unit 5/9;
- sửa booking demo #8 vượt capacity.

### Nếu database chưa chạy 009–012
Chạy migration còn thiếu theo đúng thứ tự:

```text
009_security_hardening.sql
010_content_image_refresh.sql
011_gallery_capacity_logic_fix.sql
012_production_polish.sql
013_final_consistency_hardening.sql
```

**Không chạy lại `002_seed.sql` trên production đang có dữ liệu thật**, vì seed development có TRUNCATE/reset demo data.

## 5. Server requirements

- PHP 8+.
- PHP cURL enabled.
- Apache `.htaccess` / mod_rewrite hoạt động.
- HTTPS.
- PHP Session hoạt động.
- outbound HTTPS tới Supabase được hosting cho phép.

## 6. Entry point / URL

Production canonical:

```text
https://yanghomestay.helioho.st/customer/
```

Root `/` redirect về `/customer/`.
Explicit `/customer/index.html` được `.htaccess` canonicalize về `/customer/`.

## 7. Smoke test bắt buộc sau deploy

Mở bằng cửa sổ Incognito hoặc Ctrl+F5 sau khi deploy.

### P0 — data/config
1. `/customer/`
   - homepage load;
   - không có `Server configuration error`.
2. `/customer/homestays.html?tag=workcation`
   - có kết quả hoặc empty state đúng;
   - không có 500/config error.
3. `/customer/unit-detail.html?id=14`
   - Cau Giay Business Apartment;
   - 2 khách;
   - 1 phòng ngủ;
   - 1 giường;
   - gallery 6 ảnh;
   - bedroom và workspace rõ.
4. `/customer/experience-detail.html?slug=hanoi-food`
   - Ẩm thực Hà Nội;
   - không redirect sang homestay.

### Auth
5. `/customer/login.html`
   - login đúng;
   - sai password báo lỗi credential, không báo config error;
   - không hiện 2 toast duplicate.
6. Register/logout/session refresh.
7. Test direct URL/API cho customer → admin/manager/staff phải bị chặn.

### Booking
8. Chọn ngày hợp lệ → quote.
9. checkout <= checkin → validation.
10. khách > capacity → validation.
11. ngày overlap → unavailable/conflict.
12. booking success → wording “yêu cầu đặt chỗ”.
13. My Booking → detail → cancel pending/confirmed.

### Internal portal
14. Admin login/dashboard.
15. Manager login/dashboard.
16. Receptionist/staff login/dashboard.
17. Cross-role action bị backend chặn đúng RBAC.

## 8. Responsive smoke test thật trên Chrome

Do browser trong môi trường audit bị policy chặn, bạn cần test production ở:

```text
360, 390, 430, 768, 1024, 1366, 1440 px
```

Ưu tiên:
- navbar/menu mobile;
- hero/search;
- filter drawer/listing;
- unit gallery;
- booking card/sticky area;
- login/register;
- YangMatch;
- My Booking;
- internal dashboard/table.

Pass khi:
- không horizontal scroll ngoài ý muốn;
- CTA không bị che;
- modal/form không vượt viewport;
- text/card không overlap;
- ảnh crop hợp lý.

## 9. Cache

Sau upload:
- Ctrl+F5 hoặc Incognito.
- Nếu HelioHost/Plesk có cache, purge cache nếu có option.
- Không dùng URL có cache cũ để kết luận source mới chưa hoạt động trước khi hard refresh.

## 10. GitHub / portfolio safety

Chỉ commit/upload public:

```text
YangHomestay-FINAL.zip
```

Không commit:

```text
backend/config.supabase.local.php
YangHomestay-HELIOHOST-DEPLOY.zip
YangHomestay-Supabase-Config-Hotfix*.zip
```

Trước khi push GitHub, kiểm tra `git status` và bảo đảm private config không được track.

## 11. Nếu sau deploy vẫn thấy lỗi datasource

Kiểm tra theo thứ tự:
1. `backend/config.supabase.local.php` có thật và PHP đọc được.
2. PHP cURL enabled.
3. Supabase project URL/key còn hiệu lực.
4. outbound HTTPS từ HelioHost tới Supabase hoạt động.
5. migration/schema phù hợp.
6. xem server error log — **không copy secret ra nơi công khai**.

Frontend hiện trả 503 thân thiện khi config thiếu; nếu vẫn thấy literal `Server configuration error`, gần như chắc chắn browser/server đang chạy source cũ hoặc cache cũ.
