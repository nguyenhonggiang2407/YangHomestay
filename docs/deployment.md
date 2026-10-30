# Triển khai YangHomestay

## Yêu cầu

- Apache với mod_rewrite và quyền đọc .htaccess.
- PHP 8 trở lên, cURL, Session và kết nối HTTPS ra Supabase.
- Database Supabase PostgreSQL đã chạy schema và các migration cần thiết.

## Đặt mã nguồn

Đặt nội dung repository trong document root của domain, thường là httpdocs.
Các thư mục customer, admin, manager, staff, backend và assets phải cùng cấp với index.php.

Sao chép:

```text
backend/config.supabase.example.php
→ backend/config.supabase.local.php
```

Điền URL và khóa backend Supabase. Có thể dùng các biến môi trường
`SUPABASE_URL` và `SUPABASE_SECRET_KEY` theo cấu hình của backend.
Khóa này không được đưa vào JavaScript hoặc tệp có thể tải công khai.

## Database

Với database trống, chạy các tệp SQL trong database/supabase theo thứ tự số.
Tệp 002_seed.sql tạo dữ liệu phát triển và có thao tác đặt lại dữ liệu;
chỉ dùng ở database thử hoặc lần cài mới.

Với hệ thống đang sử dụng, sao lưu trước và chỉ áp dụng migration còn thiếu.
Ví dụ, database đã có migration 012 chỉ cần áp dụng:

```text
database/supabase/013_final_consistency_hardening.sql
```

Migration 013 điều chỉnh ánh xạ ảnh, thông tin cảnh hồ và dữ liệu booking mẫu.
Không đổi tên các migration đã chạy.

## Kiểm tra sau khi cập nhật

1. Mở /customer/, tìm phòng theo ngày và số khách.
2. Mở chi tiết căn; kiểm tra giá, sức chứa và thư viện ảnh.
3. Đăng ký, đăng nhập và đăng xuất.
4. Gửi một yêu cầu đặt chỗ trên dữ liệu thử; kiểm tra ngày không hợp lệ,
   số khách vượt sức chứa và khoảng ngày đã kín.
5. Thử tài khoản của từng vai trò ở /staff/, /manager/ và /admin/.
6. Kiểm tra menu, bảng và biểu mẫu ở màn hình điện thoại.

Nếu gặp lỗi cấu hình, kiểm tra tệp config, PHP cURL, kết nối HTTPS đến Supabase,
sau đó xem error log trên máy chủ. Khi thay JavaScript hoặc CSS, tải lại trang
bỏ qua cache trước khi kiểm tra.

Xem thêm [kiểm thử](testing.md) và [database](../database/supabase/README.md).
