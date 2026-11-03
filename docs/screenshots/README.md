# Screenshot ứng dụng — 03/11/2026

Các file JPEG được chụp bằng Chromium từ ứng dụng HTTPS đang chạy; không dựng lại
UI hoặc thay dữ liệu hiển thị bằng mockup. Đây là trạng thái demo tại thời điểm
chụp, không phải bằng chứng giao dịch hoặc phòng đang được kinh doanh.

| File | Trang | Viewport |
| --- | --- | --- |
| `home-desktop.jpg` | `https://yanghomestay.helioho.st/customer/` | 1440 × 1000 |
| `home-mobile.jpg` | Cùng trang chủ | 390 × 844 |
| `search-desktop.jpg` | `/customer/homestays.html?guests=2` | 1440 × 1000 |
| `search-mobile.jpg` | Cùng tìm kiếm, cuộn đến kết quả | 390 × 844 |
| `unit-detail-desktop.jpg` | `/customer/unit-detail.html?id=10` | 1440 × 1000 |
| `unit-detail-mobile.jpg` | Cùng chi tiết Dong Da Cozy Studio | 390 × 844 |

Kiểm tra lúc chụp: nội dung API đã tải, không có HTTP 4xx/5xx ở các request quan
sát được, không có ảnh hỏng được phát hiện trên trang và không tràn ngang ở các
viewport trên. Search/detail không phát sinh lỗi JavaScript được quan sát.
Đây là kiểm tra các màn hình công khai, chưa kiểm chứng thao tác đặt chỗ hoặc
portal đã đăng nhập.

Ảnh phòng trong screenshot là asset stock hiện có của demo. Giữ nguyên
[catalog nguồn ảnh](../image-sources.md); lần cập nhật này không thay giấy phép
ảnh hoặc tuyên bố đã xác minh lại toàn bộ catalog.
