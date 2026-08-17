# 🔁 Migration Notes — `hotel_management` → `yang_homestay`

Tài liệu map dữ liệu hệ thống cũ sang kiến trúc mới của YangHomestay.

> ⚠️ **Phase 2 KHÔNG thực hiện migration dữ liệu.** File này chỉ là bản đồ thiết kế
> cho Phase 3+. Database cũ `hotel_management` **giữ nguyên**, website Phase 1 vẫn chạy bình thường.

---

## 1. Kiến trúc cũ vs mới

```
CŨ (hotel_management)                    MỚI (yang_homestay)
──────────────────────────────           ─────────────────────────────────
Hotel (1 tòa)                            Property (nhiều cơ sở)
Room (101, 102...)                       Unit (căn theo "gu", theo property)
users (admin)                            admins
customer_users (portal)                  customers
customers (khách walk-in)                → gộp vào customers (hoặc TRANSFORM)
bookings / customer_bookings             bookings + booking_nights + booking_price_items
rooms.status = occupied                  KHÔNG còn — suy ra từ booking theo ngày
room_gallery                             unit_images / property_images
reviews (room_id, không cần booking)     reviews (bắt buộc booking_id — verified stay)
activity_logs                            audit_logs (admin) + login_logs (actor_type)
```

---

## 2. Map bảng → bảng

| Bảng cũ | Bảng mới | Phân loại | Ghi chú |
|---|---|---|---|
| `users` | `admins` | **MIGRATE** | Map role: `admin → super_admin`, `manager → manager`, `staff → staff`. Password hash (bcrypt) chuyển thẳng. |
| `customer_users` | `customers` | **TRANSFORM** | Bỏ `idcard`, `date_of_birth`, `nationality`, `address` (data minimization). Chuyển `fullname → full_name`, thêm `email_verified_at`, status `blocked`. |
| `customers` (khách walk-in, có `idcard`) | `customers` | **TRANSFORM** | Gộp vào bảng khách hàng mới, bỏ CCCD/DOB khỏi account. |
| `rooms` | `units` | **TRANSFORM** | `room_number → code/name`, thêm property_id (gán vào property phù hợp), tách giá snapshot, bỏ `status=occupied`. `room_type → type`. |
| `bookings` | `bookings` | **TRANSFORM** | `room_id → unit_id`; tách `price_per_night` ra `booking_nights`; `total_price → subtotal + cleaning_fee − discount_amount`; thêm `payment_status`, `promotion_id`, timestamps trạng thái. |
| `customer_bookings` | `bookings` | **MIGRATE** | Gộp 2 nguồn booking (admin + portal) về 1 bảng `bookings`. Giữ `booking_reference`, `special_requests`. |
| `room_gallery` | `unit_images` | **TRANSFORM** | Thêm `image_category`, `is_cover`; gán ảnh theo unit thay vì room. |
| `reviews` (room_id) | `reviews` | **TRANSFORM** | Bắt buộc `booking_id` (1 booking → 1 review), thêm rating chi tiết 6 khía cạnh, `approved_by_admin_id`. |
| `login_logs` | `login_logs` | **MIGRATE** | Thêm `actor_type` (customer/admin). |
| `activity_logs` | `audit_logs` | **TRANSFORM** | Chỉ log thao tác admin, thêm `old_value_json`/`new_value_json`; log khách hàng chuyển sang `notifications`/`login_logs`. |

---

## 3. Phân loại dữ liệu

### 🔁 REUSE (dùng lại nguyên trạng)
- **`admins`**: username, email, password_hash (bcrypt) — chuyển thẳng.
- **`customer_bookings.booking_reference`**: chuẩn mã đặt phòng giữ nguyên định dạng.
- **`login_logs`**: lịch sử đăng nhập admin.

### 📦 MIGRATE (chuyển có kiểm soát)
- **`users` → `admins`** (map role, giữ password hash).
- **`customer_users` → `customers`** (bỏ bớt trường nhạy cảm).
- **`customer_bookings` → `bookings`** (gộp với bookings admin).

### 🔄 TRANSFORM (chuyển đổi cấu trúc)
- **`rooms` → `units`** + gán `property_id` + snapshot giá.
- **`bookings.total_price` → tách thành** `booking_nights` (giá từng đêm) + `booking_price_items` (dòng tiền).
- **`reviews`**: thêm ràng buộc `booking_id` → trở thành review có "verified stay".
- **`activity_logs` → `audit_logs`** (admin) + `notifications` (khách).

### 🗑 IGNORE (không chuyển)
- **`rooms.status = occupied`**: khái niệm bị loại bỏ — thay bằng query booking theo ngày.
- **`customers.idcard / date_of_birth / nationality / address`** trên account: vi phạm data minimization.
- **`room_gallery` ảnh placeholder via.placeholder.com**: thay bằng asset thật/placeholder local.

### 🏛 LEGACY (giữ nguyên, không đụng)
- Toàn bộ database `hotel_management` trong Phase 2 — chỉ migrate ở Phase 3+ khi backend sẵn sàng.
- Website Phase 1 vẫn chạy trên `hotel_management` cho tới khi Phase 3 chuyển xong.

---

## 4. Dữ liệu nào KHÔNG thể migrate

| Dữ liệu | Lý do |
|---|---|
| Booking đã lưu chỉ có `price_per_night` (1 giá) | Không biết giá từng đêm → chỉ có thể ghi 1 dòng `booking_nights` với giá trung bình hoặc ghi chú legacy |
| Review cũ không gắn booking | Không đạt "verified stay" — chỉ import nếu có booking tương ứng, ngược lại để `pending` hoặc bỏ qua |
| `rooms.status = occupied` tại thời điểm migrate | Phải đối chiếu thủ công với booking đang hoạt động |
| Ảnh placeholder / ảnh ngoài | Không nhập — chờ asset thật |

---

## 5. Lộ trình migrate đề xuất (Phase 3+)

1. **Freeze** ghi mới vào `hotel_management` (chế độ read-only).
2. Import `yang_homestay_schema.sql` (đã xong ở Phase 2).
3. Chạy script migrate: admins → customers → properties (tạo 1 property "YangHomestay" mặc định cho rooms cũ) → units → bookings (+nights, +price items) → reviews.
4. Verify bằng query đối chiếu: tổng tiền, số đêm, trạng thái.
5. Chuyển `backend/config.php` sang `yang_homestay` (đây là **Phase 3**).
6. Giữ `hotel_management` như backup tới khi production ổn định.

> ⚠️ KHÔNG chạy bất kỳ bước nào ở trên trong Phase 2.
