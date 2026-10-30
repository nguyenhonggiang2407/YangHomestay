# Kiểm thử YangHomestay

## Các bài kiểm tra cục bộ

Chạy từ thư mục gốc của repository:

```bash
php backend/tests/test-pricing.php
php backend/tests/test-availability.php
php backend/tests/test-rbac.php
node --check customer/js/api.js
node --check customer/js/ui.js
node --check admin/js/admin.js
node --check staff/js/staff.js
```

Ba bài kiểm tra PHP kiểm tra cách tính giá, khoảng ngày đặt phòng và quyền theo vai trò.

## Kiểm tra với Supabase

`backend/tests/live-test.php` gọi các service trên database đã cấu hình.
Bài kiểm tra tạo tài khoản và booking thử, rồi dọn dữ liệu đó.
Chỉ chạy trên database thử có bộ seed tương ứng:

```bash
php backend/tests/live-test.php
```

`backend/tests/concurrency-test.php` kiểm tra hai yêu cầu đặt cùng căn, cùng ngày.
Kết quả mong đợi là một yêu cầu thành công và một xung đột.
Bài này cần môi trường chạy được hai tiến trình PHP và database thử riêng.

## Các luồng cần thử trên trình duyệt

- Tìm theo ngày, khu vực và số khách.
- Ảnh và thông tin căn khớp với dữ liệu.
- Đăng nhập, đăng xuất và truy cập chéo giữa các vai trò.
- Báo giá, gửi yêu cầu đặt chỗ, xem lại và hủy yêu cầu theo trạng thái cho phép.
- Menu, modal và bảng không tràn ở màn hình nhỏ.

Các lệnh trên là cách kiểm tra dự án; kết quả phụ thuộc phiên bản mã nguồn
và môi trường đang chạy. Kiểm tra cú pháp không thay cho kiểm tra luồng đặt phòng.
