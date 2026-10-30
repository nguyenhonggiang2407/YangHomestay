# Database MySQL tham khảo

Database mới hoàn toàn riêng biệt cho nền tảng **YangHomestay**.

> **Tagline:** *Ở đúng gu, sống đúng chất Hà Nội.*

Kiến trúc dữ liệu: **PROPERTY → UNIT → AVAILABILITY → BOOKING**

```
YangHomestay Trúc Bạch (property)
├── Lake View Studio        (unit)
├── Balcony Couple Studio   (unit)
└── Family Apartment        (unit)
```

`Property` = cơ sở / vị trí. `Unit` = căn thực tế mà khách đặt. **Booking luôn đặt theo `unit_id`.**

---

## 1. Các file trong thư mục này

| File | Mục đích |
|---|---|
| `yang_homestay_schema.sql` | Schema: database, tables, foreign keys, indexes, constraints. **Không chứa dữ liệu demo.** |
| `yang_homestay_seed.sql` | Dữ liệu development/demo. Production **chỉ import schema**, không cần seed. |
| `012_production_polish_mysql.sql` | Canonical gallery/data polish cho MySQL hiện có. |
| `013_final_consistency_hardening_mysql.sql` | Migration chốt: bỏ ảnh trùng Unit 13, chuẩn hóa claim Hồ Tây/Trúc Bạch và sửa booking demo vượt capacity. |
| `README.md` | File này — hướng dẫn + quy ước. |
| `migration-notes.md` | Map dữ liệu cũ (`hotel_management`) → kiến trúc mới. |
| `ERD.md` | Sơ đồ quan hệ thực thể (Mermaid). |
| `yang_homestay.sql` | *(Cũ)* bản nháp ban đầu, đã thay thế bởi `yang_homestay_schema.sql` + `yang_homestay_seed.sql`. Không xóa, không dùng tiếp. |

---

## 2. Hướng dẫn import

### Bước 1 — Import schema

**phpMyAdmin:**
1. Mở http://localhost/phpmyadmin
2. Tab **Import**
3. Chọn file `yang_homestay_schema.sql`
4. Nhấn **Go**

**Hoặc CLI (XAMPP):**
```bash
"C:\xampp\mysql\bin\mysql.exe" -u root -p < yang_homestay_schema.sql
```

### Bước 2 — Import seed (chỉ development/demo)

Lặp lại với `yang_homestay_seed.sql`.

### Bước 3 — Kiểm tra tables

```sql
USE yang_homestay;
SHOW TABLES;                       -- 24 bảng
SELECT COUNT(*) FROM properties;   -- 5
SELECT COUNT(*) FROM units;        -- 16
SELECT COUNT(*) FROM bookings;     -- 12
```

### Bước 4 — Reset development database

Chạy lại **seed** (file seed tự TRUNCATE toàn bộ bảng của `yang_homestay` rồi insert lại). Không cần drop database.

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root -p < yang_homestay_seed.sql
```

Hoặc reset hoàn toàn từ đầu:
```sql
DROP DATABASE yang_homestay;   -- CHỈ yang_homestay, KHÔNG bao giờ đụng hotel_management
```
rồi import lại schema + seed.

> ⚠️ Seed chỉ chạy trong database `yang_homestay`. Nó không bao giờ đụng tới `hotel_management`.

### Bước 5 — Không dùng mật khẩu demo ở production

```
⚠️  CHANGE DEMO PASSWORD BEFORE PRODUCTION ⚠️
```

| Tài khoản | Mật khẩu demo | Role |
|---|---|---|
| `admin` | `admin123` | super_admin |
| `manager` | `manager123` | manager |
| `staff` | `staff123` | staff |
| mọi customer demo | `customer123` | — |

Password lưu dạng **bcrypt**, tương thích `password_hash()` / `password_verify()` của PHP.

---

## 3. Property vs Unit

| Khái niệm | Ý nghĩa | Ví dụ |
|---|---|---|
| `properties` | Cơ sở / tòa nhà / vị trí | YangHomestay Phố Cổ (Hoàn Kiếm) |
| `units` | Căn thực tế khách đặt | Old Quarter Balcony Studio |

- `properties`: code, name, slug, district, ward, address, lat/lng, checkin_time, checkout_time, status, featured…
- `units`: property_id, code, name, slug, type, base_price, cleaning_fee, max_guests, bedrooms, beds, bathrooms, area_m2, status, featured…
- **Không dùng trạng thái `occupied` cho unit.** Unit có khách hay không phải xác định qua booking theo từng ngày.
- Unit có lịch sử booking → **không xóa cứng**, dùng `status = 'inactive'` (soft disable).

---

## 4. Quy ước ngày tháng (toàn hệ thống)

```
check_in  = inclusive (đêm đầu được tính)
check_out = exclusive (không tính đêm cuối)
```

Ví dụ: `20/08 → 23/08` = **3 đêm** (20, 21, 22). Khách khác có thể check-in từ `23/08`.

Áp dụng cho:
- `bookings.check_in` / `bookings.check_out`
- `availability_blocks.start_date` (inclusive) / `end_date` (exclusive)
- `booking_nights.stay_date` (mỗi dòng = 1 đêm thực tế)

---

## 5. Availability — logic chống đặt trùng

Không có bảng availability riêng; availability được **tổng hợp** từ dữ liệu thật:

> Unit **khả dụng** cho 1 đêm ⟺ không có booking active chứa đêm đó **VÀ** không có availability_block chứa đêm đó.

### Overlap logic

```sql
SELECT COUNT(*)
FROM bookings
WHERE unit_id = ?
  AND status IN ('pending', 'confirmed', 'checkin')
  AND new_check_in  < check_out
  AND new_check_out > check_in;
```

- Chỉ `pending | confirmed | checkin` chặn availability.
- `cancelled | checkout` **không** chặn.
- Composite index `idx_bookings_availability (unit_id, status, check_in, check_out)` phục vụ query này.

### Availability blocks

`availability_blocks` dùng khi unit không bán được vì: `maintenance | owner_use | cleaning | manual_block | other`.
Ví dụ block `20/08 → 23/08` chặn các đêm 20, 21, 22; bán lại từ 23/08.

---

## 6. Booking — trạng thái

| Status | Ý nghĩa | Chặn availability? |
|---|---|---|
| `pending` | Chờ xác nhận | ✅ |
| `confirmed` | Đã xác nhận | ✅ |
| `checkin` | Khách đang lưu trú | ✅ |
| `checkout` | Đã trả phòng | ❌ |
| `cancelled` | Đã hủy | ❌ |

| Payment status | Ý nghĩa |
|---|---|
| `unpaid` | Chưa thanh toán |
| `partial` | Thanh toán một phần |
| `paid` | Đã thanh toán đủ |
| `refunded` | Đã hoàn tiền |

`booking_reference` do **backend generate** , ví dụ `YH202608160001` — database chỉ cần `UNIQUE`.

---

## 7. Pricing — giá snapshot theo từng đêm

Giá không phải 1 con số cố định; mỗi đêm của booking được khóa lại trong `booking_nights`:

```
booking 21/08 → 24/08 (3 đêm):
  21/08 = 850.000 ₫
  22/08 = 1.050.000 ₫   (cuối tuần)
  23/08 = 1.050.000 ₫   (cuối tuần)
```

**Hệ quả:** admin đổi giá tương lai **KHÔNG** làm booking cũ đổi giá.

Công thức:
```
subtotal        = SUM(booking_nights.nightly_rate)
total_amount    = subtotal + cleaning_fee − discount_amount
number_of_nights = DATEDIFF(check_out, check_in)
```

`booking_price_items` lưu dòng tiền để hiển thị hóa đơn (accommodation / cleaning_fee / discount / extra_guest / other):

```
3 đêm                2.700.000 ₫
Cleaning fee           100.000 ₫
Discount              -150.000 ₫
───────────────────────────────
Total                2.650.000 ₫
```

---

## 8. Rate rules — luật giá

`rate_rules` cho phép: weekday / weekend / holiday / seasonal / special event.

- `day_of_week`: **1 = Chủ nhật … 7 = Thứ 7** (quy ước `DAYOFWEEK()` của MySQL), `NULL` = mọi ngày trong tuần.
- `start_date` / `end_date`: `NULL` = không giới hạn.
- `price` = giá đêm khi rule áp dụng (thay thế `base_price` của unit).
- **Khi overlap: `priority` cao hơn thắng.**
- Cùng priority → rule **cụ thể hơn** thắng:
  1. có `day_of_week` > `NULL`
  2. khoảng ngày hẹp hơn > rộng hơn
  3. cuối cùng: `updated_at` mới hơn (deterministic).
- Khi tạo booking, backend tính giá từng đêm rồi ghi `booking_nights` (kèm `rate_rule_id`).

---

## 9. Promotion — luật áp dụng

- `discount_type`: `percentage` | `fixed_amount`.
- `discount_value`: % hoặc số tiền VND. `maximum_discount` = trần chiết khấu (áp dụng cho %).
- `minimum_booking_value`: giá trị đơn tối thiểu để dùng mã.
- `valid_from` / `valid_until`: hạn dùng.
- `usage_limit` (toàn mã) và `usage_limit_per_customer`.
- Khi áp dụng: ghi `bookings.promotion_id` + `promotion_code_snapshot`, ghi 1 dòng `promotion_usages` (unique theo `booking_id`), và `booking_price_items` dòng `discount` âm.
- Mã hết hạn / hết lượt / vượt giá trị tối thiểu → từ chối (validate ở backend).
- Seed chỉ có 2 mã **DEMO**: `YANGNEW10` (10%) và `HANOI150` (150.000₫).

---

## 10. Review — xác minh "Verified stay"

- 1 booking → tối đa 1 review (`UNIQUE reviews.booking_id`).
- Backend chỉ cho review khi: `bookings.status = 'checkout'` **và** `bookings.customer_id = user đang đăng nhập`.
- Badge "✓ Verified stay" **suy ra được** từ booking — không cần cột riêng.
- Review phải được duyệt (`pending → approved`) trước khi hiển thị công khai.

---

## 11. YangMatch — experience tags

`experience_tags` + `unit_experience_tags (weight 1-10)` là dữ liệu đầu vào của YangMatch.

```
Old Quarter Balcony Studio:   couple=10, old-quarter=10, food-lover=9, photography=8, nightlife=7
West Lake Workcation Loft:    workcation=10, quiet=9, lake-view=9, long-stay=8, business=8
```

Categories: `guest_type | style | activity | location | work`.

---

## 12. Demo data policy

- Seed **chỉ** dùng địa chỉ demo ở mức district/neighborhood, ghi rõ "(địa chỉ demo)".
- Email demo dùng domain `example.com` (chuẩn RFC 2606) — không gửi được mail thật.
- Ảnh demo dùng **asset local đã curate** trong `assets/images/` (nguồn Pexels/crop local được ghi ở `docs/image-sources.md`) — website không hotlink ảnh runtime.
- Khoảng cách / `walking_minutes` của `nearby_places` là **ước lượng DEMO** — thay dữ liệu thật trước production.
- Nội dung mô tả property/unit bằng tiếng Việt ngắn gọn, hợp lý — không lorem ipsum.
- **Không bao giờ** seed dữ liệu giả hàng loạt (review giả, booking giả…) vào production.

---

## 13. Foreign key policy (quyết định thiết kế)

| Relationship | Hành vi xóa | Lý do |
|---|---|---|
| `properties → units` | RESTRICT | Không mất unit; dùng soft disable |
| `units → bookings` | RESTRICT | Lịch sử booking không được mất |
| `bookings → customers` | RESTRICT | Giữ lịch sử đặt phòng |
| `bookings → reviews` | RESTRICT | Review gắn với booking thật |
| `bookings → booking_nights / price_items` | CASCADE | Dữ liệu thuộc về booking |
| `units → unit_images / amenities / tags / blocks / rate_rules` | CASCADE | Dữ liệu phụ thuộc unit |
| `bookings.promotion_id` | SET NULL | Giữ booking khi xóa mã |
| `booking_nights.rate_rule_id` | SET NULL | Giữ snapshot giá khi xóa rule |
| `reviews.approved_by_admin_id`, `audit_logs.admin_id`, `blocks.created_by_admin_id` | SET NULL | Giữ bản ghi khi xóa admin |
| `login_logs` | không FK | Log phải tồn tại dù account bị xóa |

Nguyên tắc chung: **lịch sử không bị xóa theo tài khoản/căn hộ** — ưu tiên soft disable (`status = 'inactive'`) hơn là xóa cứng.

---

## 14. Timestamps & timezone

- Các bảng quan trọng đều có `created_at` / `updated_at`.
- Không ép timezone phức tạp vào SQL — **application** (Phase sau) xử lý timezone.
- YangHomestay hoạt động chủ yếu theo `Asia/Ho_Chi_Minh`.

---

## 15. Yêu cầu môi trường

- MySQL 5.7+ **hoặc** MariaDB 10.2+ (khuyến nghị MariaDB 10.4+/MySQL 8+).
- PHP 8+.
- Charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
- Không phụ thuộc PostgreSQL / cloud database.
