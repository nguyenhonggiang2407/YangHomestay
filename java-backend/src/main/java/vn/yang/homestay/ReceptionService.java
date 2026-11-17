package vn.yang.homestay;

import java.time.LocalDate;
import java.time.format.DateTimeParseException;
import java.util.Map;
import java.util.Set;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.ResultSetExtractor;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;
import tools.jackson.databind.ObjectMapper;

@Service
class ReceptionService {
  private static final LocalDate LAST_CALENDAR_START = LocalDate.of(9999, 12, 24);
  private static final Map<String, String> NEXT_STATUS =
      Map.of("pending", "confirmed", "confirmed", "checkin", "checkin", "checkout");
  private static final Set<String> BLOCK_REASONS =
      Set.of("maintenance", "owner_use", "cleaning", "manual_block", "other");
  private static final String BLOCK_COLUMNS =
      "id, unit_id, start_date::text, end_date::text, reason";

  private final JdbcTemplate jdbc;
  private final ObjectMapper json;

  ReceptionService(JdbcTemplate jdbc, ObjectMapper json) {
    this.jdbc = jdbc;
    this.json = json;
  }

  @Transactional(readOnly = true, isolation = Isolation.REPEATABLE_READ)
  public Object calendar(String from) {
    LocalDate today = jdbc.queryForObject("select current_date", LocalDate.class);
    LocalDate start = from == null || from.isBlank() ? today : calendarStart(from);
    LocalDate end = start.plusDays(7);
    var units = jdbc.queryForList("""
        select u.id, u.name, p.district
        from public.units u
        join public.properties p on p.id = u.property_id
        where u.status = 'active' and p.status = 'active'
        order by p.district, u.name, u.id
        """);
    var bookings = jdbc.queryForList("""
        select b.id, b.unit_id, b.check_in::text, b.check_out::text, b.status, b.booking_reference
        from public.bookings b
        join public.units u on u.id = b.unit_id
        join public.properties p on p.id = u.property_id
        where u.status = 'active' and p.status = 'active'
          and b.status <> 'cancelled' and b.check_in < ? and b.check_out >= ?
        order by b.check_in, b.id
        """, end, start);
    var blocks = jdbc.queryForList("""
        select a.id, a.unit_id, a.start_date::text, a.end_date::text, a.reason
        from public.availability_blocks a
        join public.units u on u.id = a.unit_id
        join public.properties p on p.id = u.property_id
        where u.status = 'active' and p.status = 'active' and a.start_date < ? and a.end_date > ?
        order by a.start_date, a.id
        """, end, start);
    return Map.of(
        "from", start.toString(), "to", end.toString(), "today", today.toString(),
        "units", units, "bookings", bookings, "blocks", blocks);
  }

  @Transactional(isolation = Isolation.READ_COMMITTED)
  public Object transition(long adminId, long bookingId, String expectedStatus, String status) {
    if (expectedStatus == null || !NEXT_STATUS.containsKey(expectedStatus)
        || !NEXT_STATUS.get(expectedStatus).equals(status)) {
      throw new ApiProblem(422, "Không thể chuyển sang trạng thái đã chọn.");
    }
    var bookings = jdbc.query("""
        select status, check_in, check_out, current_date as today
        from public.bookings where id = ? for update
        """, (row, index) -> new BookingStatus(
            row.getString("status"), row.getObject("check_in", LocalDate.class),
            row.getObject("check_out", LocalDate.class), row.getObject("today", LocalDate.class)),
        bookingId);
    if (bookings.isEmpty()) throw new ApiProblem(404, "Không tìm thấy đơn đặt.");
    BookingStatus booking = bookings.get(0);
    if (!booking.status().equals(expectedStatus)) {
      throw new ApiProblem(409, "Đơn đặt vừa thay đổi. Vui lòng tải lại lịch.");
    }
    if (status.equals("checkin")) {
      if (booking.today().isBefore(booking.checkIn())) {
        throw new ApiProblem(422, "Chưa đến ngày nhận phòng.");
      }
      if (!booking.today().isBefore(booking.checkOut())) {
        throw new ApiProblem(422, "Kỳ lưu trú đã kết thúc.");
      }
    }
    if (status.equals("checkout") && booking.today().isBefore(booking.checkOut())) {
      throw new ApiProblem(422, "Chưa đến ngày trả phòng.");
    }

    int updated = jdbc.update("""
        update public.bookings
        set status = ?,
            confirmed_at = case when ? = 'confirmed' then now() else confirmed_at end,
            checked_in_at = case when ? = 'checkin' then now() else checked_in_at end,
            checked_out_at = case when ? = 'checkout' then now() else checked_out_at end
        where id = ? and status = ?
        """, status, status, status, status, bookingId, expectedStatus);
    if (updated != 1) throw new ApiProblem(409, "Đơn đặt vừa thay đổi. Vui lòng tải lại lịch.");
    audit(adminId, "CHANGE_BOOKING_STATUS", "bookings", bookingId,
        Map.of("status", booking.status()), Map.of("status", status));
    return Map.of("id", bookingId, "status", status);
  }

  @Transactional(isolation = Isolation.READ_COMMITTED)
  public Object addBlock(long adminId, long unitId, String start, String end, String reason) {
    if (unitId <= 0 || unitId > Integer.MAX_VALUE) {
      throw new ApiProblem(422, "Mã căn không hợp lệ.");
    }
    if (reason == null || !BLOCK_REASONS.contains(reason)) {
      throw new ApiProblem(422, "Lý do đóng ngày không hợp lệ.");
    }
    StayDates stay = StayDates.parse(start, end, jdbc);
    lockUnit(unitId);
    var available = jdbc.queryForList("""
        select public.is_unit_available(u.id, ?, ?)
        from public.units u
        join public.properties p on p.id = u.property_id
        where u.id = ? and u.status = 'active' and p.status = 'active'
        """, Boolean.class, stay.checkIn(), stay.checkOut(), unitId);
    if (available.isEmpty()) throw new ApiProblem(404, "Không tìm thấy căn homestay.");
    if (!available.get(0)) {
      throw new ApiProblem(409, "Khoảng ngày trùng với đơn đặt hoặc lịch đóng đã có.");
    }
    var block = jdbc.queryForMap("""
        insert into public.availability_blocks (unit_id, start_date, end_date, reason, created_by_admin_id)
        values (?, ?, ?, ?, ?)
        returning
        """ + BLOCK_COLUMNS, unitId, stay.checkIn(), stay.checkOut(), reason, adminId);
    audit(adminId, "BLOCK_DATES", "availability_blocks", ((Number) block.get("id")).longValue(),
        null, block);
    return block;
  }

  @Transactional(isolation = Isolation.READ_COMMITTED)
  public void removeBlock(long adminId, long blockId) {
    var units = jdbc.queryForList(
        "select unit_id from public.availability_blocks where id = ?", Long.class, blockId);
    if (units.isEmpty()) throw new ApiProblem(404, "Không tìm thấy lịch đóng ngày.");
    lockUnit(units.get(0));
    var removed = jdbc.queryForList(
        "delete from public.availability_blocks where id = ? returning " + BLOCK_COLUMNS, blockId);
    if (removed.isEmpty()) throw new ApiProblem(404, "Không tìm thấy lịch đóng ngày.");
    audit(adminId, "UNBLOCK_DATES", "availability_blocks", blockId, removed.get(0), null);
  }

  private void lockUnit(long unitId) {
    // Keep this key in sync with create_booking_atomic so both write paths serialize by unit.
    jdbc.query("select pg_advisory_xact_lock(hashtext('yanghomestay_booking'), cast(? as integer))",
        (ResultSetExtractor<Void>) rows -> null, unitId);
  }

  private void audit(long adminId, String action, String entity, long id, Object before, Object after) {
    jdbc.update("""
        insert into public.audit_logs
            (admin_id, action, entity_type, entity_id, old_value_json, new_value_json)
        values (?, ?, ?, ?, cast(? as jsonb), cast(? as jsonb))
        """, adminId, action, entity, id,
        before == null ? null : json.writeValueAsString(before),
        after == null ? null : json.writeValueAsString(after));
  }

  private static LocalDate calendarStart(String value) {
    if (!value.matches("[0-9]{4}-[0-9]{2}-[0-9]{2}")) {
      throw new ApiProblem(422, "Ngày bắt đầu không hợp lệ.");
    }
    try {
      LocalDate date = LocalDate.parse(value);
      if (date.getYear() >= 1 && !date.isAfter(LAST_CALENDAR_START)) return date;
    } catch (DateTimeParseException invalidDate) {
      throw new ApiProblem(422, "Ngày bắt đầu không hợp lệ.");
    }
    throw new ApiProblem(422, "Ngày bắt đầu ngoài phạm vi lịch.");
  }

  private record BookingStatus(String status, LocalDate checkIn, LocalDate checkOut, LocalDate today) {}
}
