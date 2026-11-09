# Kết quả kiểm tra — 03/11/2026

Môi trường: Windows, PHP CLI 8.0.30 từ XAMPP, Node.js 24.19.0.

| Kiểm tra đã chạy | Kết quả |
| --- | --- |
| `php backend/tests/test-pricing.php` | 23 pass, 0 fail. |
| `php backend/tests/test-availability.php` | 16 pass, 0 fail sau khi sửa fixture ngày hết hạn. |
| `php backend/tests/test-rbac.php` | 14 pass, 0 fail. |
| `php -l` trên các file PHP trong backend | 42 file hợp lệ. |
| `node --check` | `customer/js/api.js`, `customer/js/ui.js`, `admin/js/admin.js`, `staff/js/staff.js` hợp lệ. |
| HTTPS công khai | Root chuyển đúng `/customer/`, trả 200; API `units&limit=1` trả 200 và dữ liệu căn. |
| Trình duyệt | Chụp thật trang chủ, tìm kiếm và chi tiết ở desktop/mobile. Xem [bằng chứng và phạm vi](screenshots/README.md). |

Availability test trước đó dùng ngày hợp lệ cố định tháng 08/2026. Đến thời điểm
kiểm tra, ngày này đã ở quá khứ nên một assertion thất bại dù validation đúng.
Fixture validation nay dùng ngày tương đối theo `Asia/Ho_Chi_Minh`; các ví dụ
overlap cố định vẫn giữ để giải thích quy ước. Không đổi logic ứng dụng.

Chưa chạy `live-test.php` hoặc `concurrency-test.php`: môi trường này chưa có
Supabase thử riêng được cấu hình. Các script này tạo dữ liệu và không nên dùng
database đang phục vụ demo để kiểm tra một cách tùy tiện. Chưa kiểm chứng thanh
toán, luồng nội bộ đã đăng nhập hoặc đặt chỗ từ đầu đến cuối.

Các kết quả trên ghi lại lần kiểm tra local và demo ngày 03/11/2026.
Xem [GitHub Actions](https://github.com/nguyenhonggiang2407/YangHomestay/actions/workflows/ci.yml)
để đối chiếu kết quả lint và các test cục bộ theo commit. Kiểm tra RPC với Supabase
và kiểm tra luồng đã đăng nhập có môi trường và kết quả riêng.
