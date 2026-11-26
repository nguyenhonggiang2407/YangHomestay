package vn.yang.homestay;

import java.time.LocalDate;
import java.time.format.DateTimeParseException;
import java.time.temporal.ChronoUnit;
import org.springframework.jdbc.core.JdbcTemplate;

record StayDates(LocalDate checkIn, LocalDate checkOut, int nights) {
  private static final int MAX_NIGHTS = 366;

  static StayDates parse(String checkIn, String checkOut, JdbcTemplate jdbc) {
    if (checkIn == null || checkIn.isBlank() || checkOut == null || checkOut.isBlank()) {
      throw new ApiProblem(422, "Vui lòng chọn cả ngày nhận và ngày trả phòng.");
    }
    LocalDate arrival = date(checkIn, "Ngày nhận phòng không hợp lệ (YYYY-MM-DD).");
    LocalDate departure = date(checkOut, "Ngày trả phòng không hợp lệ (YYYY-MM-DD).");
    long nights = ChronoUnit.DAYS.between(arrival, departure);
    if (nights <= 0) {
      throw new ApiProblem(422, "Ngày trả phòng phải sau ngày nhận phòng.");
    }
    if (nights > MAX_NIGHTS) {
      throw new ApiProblem(422, "Thời gian lưu trú tối đa là " + MAX_NIGHTS + " đêm.");
    }
    LocalDate today = jdbc.queryForObject("select current_date", LocalDate.class);
    if (arrival.isBefore(today)) {
      throw new ApiProblem(422, "Ngày nhận phòng phải là hôm nay hoặc trong tương lai.");
    }
    return new StayDates(arrival, departure, (int) nights);
  }

  private static LocalDate date(String value, String message) {
    if (!value.matches("[0-9]{4}-[0-9]{2}-[0-9]{2}")) {
      throw new ApiProblem(422, message);
    }
    try {
      return LocalDate.parse(value);
    } catch (DateTimeParseException invalidDate) {
      throw new ApiProblem(422, message);
    }
  }
}
