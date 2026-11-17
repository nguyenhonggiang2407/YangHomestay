# YangHomestay Booking · Spring Boot

Bản Java 17 dùng Spring Boot, Spring Security, Spring JDBC và PostgreSQL 17. JAR phục vụ lại các trang HTML/CSS/JS của khách và giữ hợp đồng JSON của `/backend/customer-api.php`. Đường dẫn này được xử lý bởi controller Java trong bản Spring Boot.

## Chạy thử

Từ thư mục `java-backend`, dùng JDK 17, Maven 3.9 và Docker Compose.

```sh
docker compose up -d --wait
mvn package
java -jar target/yanghomestay-booking.jar
```

Mở [http://127.0.0.1:8080/customer/](http://127.0.0.1:8080/customer/). Tạo tài khoản bằng thông tin giả, tìm căn với ngày trong tương lai, xem báo giá và đặt phòng. Mở **Đơn đặt** để xem chi tiết hoặc hủy khi trạng thái cho phép.

Compose chạy PostgreSQL ở cổng `55432` trên loopback. Lần đầu nó khởi tạo volume demo mới bằng các tệp SQL dùng chung trong `database/supabase`, gồm dữ liệu mẫu. JAR kết nối bằng tài khoản riêng của database demo. Cấu hình kết nối có thể đổi qua `YANGHOMESTAY_DB_URL`, `YANGHOMESTAY_DB_USER` và `YANGHOMESTAY_DB_PASSWORD`. Dùng một database riêng đã được khởi tạo cho bản Java.

## Luồng đặt phòng

- Spring Security dùng session, BCrypt và CSRF cho đăng ký, đăng nhập, đăng xuất và thao tác ghi. API tra tài khoản hiện tại ở server để giới hạn quyền xem và hủy đơn.
- Java tính báo giá theo từng đêm dựa trên giá gốc, lịch giá và ưu đãi. Báo giá chỉ có giá trị tham khảo đến lúc đặt.
- Khi căn hết chỗ, API tìm tối đa ba khoảng cùng số đêm, với ngày nhận phòng trong 30 ngày sau ngày đã chọn. Chọn gợi ý sẽ tính lại giá và kiểm tra phòng trống.
- Khi ghi đơn, PostgreSQL dùng `create_booking_atomic` để khóa theo căn, kiểm tra lịch, tính lại giá và lưu đơn cùng giá từng đêm trong một transaction. Ngày trả phòng không tính vào kỳ ở, nên hai lượt có thể liền kề.
- Khách có thể lưu hoặc bỏ căn trong **Yêu thích**. Backend gắn danh sách với tài khoản đăng nhập. Hai yêu cầu lưu cùng căn chỉ tạo một bản ghi.
- YangMatch, gửi đánh giá và phần quản trị giá, tài khoản nội bộ tiếp tục thuộc bản PHP.

## Lịch vận hành cho lễ tân

Đặt `YANGHOMESTAY_RECEPTION_ENABLED=true` trước khi chạy JAR cục bộ, sau đó mở [màn hình lễ tân](http://127.0.0.1:8080/reception/). Với PostgreSQL demo đã khởi tạo bằng Compose, dùng tài khoản mẫu `staff / staff123` hoặc `manager / manager123`. Chỉ dùng các tài khoản mẫu này với database thử trên máy.

- **Lễ tân** xem lịch bảy ngày theo căn. Có thể xác nhận đơn, ghi nhận đến ngày nhận phòng và hoàn tất khi đến ngày trả phòng. Nếu người khác đã đổi trạng thái, hệ thống yêu cầu tải lại lịch.
- **Quản lý** và **quản trị viên** có thể đóng hoặc mở ngày cho từng căn. API đóng ngày dùng cùng khóa PostgreSQL với luồng đặt phòng để hai thao tác không ghi chồng lên nhau.
- Mỗi lần đổi trạng thái, đóng ngày hoặc mở lại ngày ghi thông tin người thao tác trong `audit_logs` cùng transaction. Phiên lễ tân có thông tin xác thực và token CSRF riêng với phiên khách.

## Kiểm thử

`mvn verify` khởi tạo PostgreSQL tạm thời để kiểm tra API, kể cả lúc Compose chưa chạy. Các ca kiểm tra bao phủ quyền lễ tân và quản lý, ghi nhật ký, hai người xác nhận cùng một đơn và tranh chấp giữa đặt phòng với đóng ngày. CI chạy cả bộ kiểm tra PHP và Java.

## Hướng phát triển

1. **YangMatch trên Java.** Lọc căn theo ngày, sức chứa và ngân sách, sau đó xếp hạng theo tiện nghi và mục đích chuyến đi. Hiển thị lý do cho từng kết quả.
2. **Thông báo theo trạng thái đơn.** Gửi xác nhận hoặc thông báo hủy sau khi transaction thành công. Kiểm tra gửi lại mà khách chỉ nhận một thông báo.
