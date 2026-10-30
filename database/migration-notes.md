# Chuyển dữ liệu từ hệ thống cũ

Ghi chú ánh xạ từ lược đồ hotel_management sang mô hình Property và Unit của YangHomestay.
Đây là tài liệu thiết kế chuyển đổi, không phải script tự động chuyển dữ liệu.

Backend hiện dùng Supabase PostgreSQL; xem [cấu trúc đang sử dụng](supabase/README.md).

| Dữ liệu cũ | Đích | Xử lý |
| --- | --- | --- |
| users | admins | Đổi tên vai trò, giữ hash mật khẩu phù hợp |
| customer_users và customers | customers | Gộp khách hàng, đối chiếu trùng email |
| rooms | units | Gán property_id, chuyển tên/mã căn và loại chỗ ở |
| bookings và customer_bookings | bookings | Đối chiếu mã, trạng thái, ngày và unit |
| price_per_night | booking_nights | Lưu giá theo từng đêm; ghi chú nếu chỉ có giá trung bình cũ |
| room_gallery | unit_images | Gán ảnh theo căn và xác định ảnh cover |
| reviews | reviews | Chỉ gắn verified stay khi đối chiếu được booking |
| activity_logs | audit_logs | Chuyển thao tác quản trị cần lưu lịch sử |
| login_logs | login_logs | Bổ sung loại tài khoản |

Không dùng rooms.status = occupied để suy ra lịch trống mới.
Lịch trống phải tính từ khoảng ngày booking và availability block.
Dữ liệu định danh không cần thiết cho tài khoản đặt phòng không đưa vào lược đồ mới.

Trước khi chuyển thật: sao lưu, thử trên database riêng, đối chiếu số bản ghi,
tổng tiền, số đêm và trạng thái, rồi mới chuyển cấu hình ứng dụng.
