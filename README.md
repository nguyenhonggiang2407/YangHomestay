# YangHomestay

Website tìm và đặt homestay tại Hà Nội, xây dựng bằng PHP, JavaScript thuần
và Supabase PostgreSQL.

[Demo](https://yanghomestay.helioho.st/)

![Trang giới thiệu YangHomestay](assets/images/homestays/hero-hanoi-boutique.jpg)

## Chức năng

- Tìm chỗ ở theo khu vực, ngày nhận/trả phòng và số khách.
- Xem ảnh, tiện nghi, sức chứa, giá và đánh giá của từng căn.
- Lưu yêu thích, gửi yêu cầu đặt chỗ và theo dõi lịch sử đặt.
- YangMatch gợi ý chỗ ở theo ngân sách, vị trí, tiện nghi và mục đích chuyến đi.
- Các khu vực làm việc riêng cho nhân viên, quản lý và quản trị viên.

YangMatch lọc theo điều kiện bắt buộc trước, sau đó tính điểm theo sở thích.
Backend tính giá đặt phòng và kiểm tra lịch trống; không dùng tổng tiền gửi từ trình duyệt.
Ngày nhận phòng được tính vào kỳ ở, ngày trả phòng không tính.

## Công nghệ

| Phần | Sử dụng |
| --- | --- |
| Giao diện | HTML, CSS, JavaScript |
| Backend | PHP 8+, Session, cURL |
| Database | Supabase PostgreSQL, REST và RPC |
| Máy chủ | Apache, mod_rewrite |

## Cài đặt

1. Đặt mã nguồn trong document root của Apache, ví dụ `C:\xampp\htdocs\YangHomestay`.
2. Bật PHP cURL và Apache mod_rewrite; cho phép sử dụng `.htaccess`.
3. Tạo database Supabase và chạy các tệp trong `database/supabase/` theo thứ tự số.
   Dữ liệu seed chỉ dùng cho database mới hoặc môi trường thử.
4. Sao chép `backend/config.supabase.example.php` thành `backend/config.supabase.local.php`,
   điền URL và khóa backend của Supabase.
5. Mở `http://localhost/YangHomestay/customer/`.

Khóa Supabase chỉ nằm ở backend; tệp cấu hình riêng đã được Git bỏ qua.
Các bước triển khai trên host và cập nhật database nằm trong [hướng dẫn triển khai](docs/deployment.md).

## Các khu vực sử dụng

| Khu vực | Đường dẫn |
| --- | --- |
| Khách hàng | `/customer/` |
| Nhân viên | `/staff/login.html` |
| Quản lý | `/manager/login.html` |
| Quản trị viên | `/admin/login.html` |

Quyền được kiểm tra ở backend qua Session, CSRF và RBAC.
Các yêu cầu đặt chỗ dùng RPC `create_booking_atomic` để kiểm tra và ghi dữ liệu trong cùng luồng.

## Cấu trúc

- `customer/`: tìm phòng, chi tiết căn, đặt chỗ và tài khoản khách.
- `admin/`, `manager/`, `staff/`: giao diện làm việc nội bộ.
- `backend/services/`: đặt phòng, giá, tài khoản, đánh giá và YangMatch.
- `backend/lib/`: kết nối Supabase, Session, CSRF và kiểm tra quyền.
- `database/supabase/`: schema, seed, chỉ mục và migration PostgreSQL.
- `database/`: tài liệu dữ liệu và lược đồ MySQL tham khảo.
- `assets/`: CSS, JavaScript và ảnh.
- `tools/`: các công cụ quản lý ảnh và dữ liệu.

## Kiểm tra

```bash
php backend/tests/test-pricing.php
php backend/tests/test-availability.php
php backend/tests/test-rbac.php
```

Xem [ghi chú kiểm thử](docs/testing.md) cho kiểm tra API, đặt phòng đồng thời
và các luồng cần thử trên trình duyệt.

## Tài liệu

- [Cấu trúc database](database/supabase/ERD.md)
- [Triển khai](docs/deployment.md)
- [Nguồn ảnh](docs/image-sources.md)
- [Bảng gán ảnh](assets/images/image-map.md)
- [Lịch sử thay đổi](CHANGELOG.md)

Dữ liệu căn và ảnh stock dùng cho demo, không đại diện cho phòng đang được kinh doanh.
Dự án chưa tích hợp cổng thanh toán.
