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
- Khi ghi đơn, PostgreSQL dùng `create_booking_atomic` để khóa theo căn, kiểm tra lịch, tính lại giá và lưu đơn cùng giá từng đêm trong một transaction. Ngày trả phòng không tính vào kỳ ở, nên hai lượt có thể liền kề.
- Trang quản trị, yêu thích, YangMatch, gửi đánh giá và các chức năng ngoài luồng khách tiếp tục thuộc bản PHP. Bản Java phục vụ giao diện và API của luồng thử riêng trên máy.

## Kiểm thử

`mvn verify` khởi tạo PostgreSQL tạm thời riêng để kiểm tra API, kể cả lúc Compose chưa chạy. Kiểm thử dùng đúng schema, RPC và dữ liệu giả của repo. CI chạy cả bộ kiểm tra PHP hiện có và bài kiểm thử Java.
