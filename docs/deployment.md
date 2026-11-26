# Triển khai YangHomestay

## Yêu cầu

- Apache với mod_rewrite và quyền đọc .htaccess.
- PHP 8 trở lên, cURL, Session và kết nối HTTPS ra Supabase.
- Database Supabase PostgreSQL đã chạy schema và các migration cần thiết.

## Đặt mã nguồn

Chỉ đặt gói web PHP trong document root của domain, thường là `httpdocs`.
Không tải toàn bộ repository lên web: mã Java, Git, SQL, tài liệu, công cụ
và cấu hình cục bộ không phải nội dung công khai.
Các thư mục `customer`, `admin`, `manager`, `staff`, `backend` và `assets`
phải cùng cấp với `index.php`.

Từ một checkout sạch đã commit, tạo ZIP chỉ chứa các tệp web được phép:

```sh
python tools/build-web-release.py ../YangHomestay-php-web.zip
```

Công cụ chỉ lấy tệp Git đang quản lý, kiểm tra tính toàn vẹn ZIP và tạo
manifest SHA-256 ở bên cạnh ZIP. `release.json` bên trong gói ghi commit
nguồn, thời gian build và runtime `php-supabase`. Trường
`javaBackendDeployed: false` phân biệt rõ bản PHP với backend Java.
`config.supabase.local.php` không nằm trong gói.

Với website đang hoạt động:

1. Sao lưu `httpdocs` vào thư mục `private` ngoài webroot.
2. Tải ZIP vào `private`, rồi giải nén đè các tệp web vào `httpdocs`.
   Giữ nguyên `backend/config.supabase.local.php` và dữ liệu upload hiện có.
3. Không chạy seed hoặc migration chỉ vì cập nhật gói giao diện.
4. Đối chiếu `/release.json` với commit dự kiến; so sánh hash các tệp web
   với manifest để xác nhận file đã được thay đúng.
5. Kiểm tra trang, API, ảnh và quyền truy cập theo danh sách bên dưới.
   Khi lỗi xuất hiện, khôi phục các tệp web từ bản sao trước triển khai.

`.htaccess` trong gói chặn mã nguồn Java/Git, SQL, công cụ, tài liệu và
archive kể cả khi các tệp này đã tồn tại từ một lần upload cũ.
CSS/JS nội bộ có phiên bản URL để trình duyệt lấy bản mới sau cập nhật.

## Phạm vi bản PHP ngày 19/11/2026

Backend PHP giữ nền commit `6af0d6a`. Giao diện YangMatch và chi tiết căn
nhận các cải tiến tương thích PHP từ commit Java `291a301`:

- YangMatch kiểm tra capability trước khi hiện tìm kiếm theo ngày và số khách.
  API PHP tiếp tục nhận bốn câu trả lời `q1`–`q4` và hiển thị giá cơ bản.
- Gợi ý ngày trống ở chi tiết căn chỉ hiện nếu API trả mảng `suggestions`.
- Phần Java/Spring Boot, lễ tân Java và PostgreSQL của Java không nằm trong
  gói này. Upload PHP không triển khai hay tự đồng bộ backend Java.

Triển khai Java cần runtime Java riêng, kết nối PostgreSQL và quy trình
migration đã được kiểm tra. Không dùng nguyên bộ seed phát triển trên
database đang hoạt động.

Sao chép:

```text
backend/config.supabase.example.php
→ backend/config.supabase.local.php
```

Chỉ tạo tệp này khi cài mới; không ghi đè cấu hình đang hoạt động khi cập nhật.
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
