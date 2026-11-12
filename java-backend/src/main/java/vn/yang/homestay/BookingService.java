package vn.yang.homestay;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.sql.SQLException;
import java.time.LocalDate;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import org.springframework.dao.DataAccessException;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

@Service
class BookingService {
  private static final Set<String> STATUSES =
      Set.of("pending", "confirmed", "checkin", "checkout", "cancelled");

  // Use the same rate ordering as create_booking_atomic so the preview and final
  // calculation agree for an unchanged catalog, including ties between rules.
  private static final String NIGHT_RATES =
      """
      SELECT u.name AS unit_name, u.cleaning_fee, u.max_guests, day.stay_date,
             COALESCE(rule.price, u.base_price) AS rate, rule.id AS rate_rule_id,
             rule.name AS rule_name
      FROM public.units u
      JOIN public.properties p ON p.id = u.property_id AND p.status = 'active'
      CROSS JOIN (
        SELECT CAST(? AS date) + n AS stay_date
        FROM generate_series(0, CAST(? AS integer) - 1) AS nights(n)
      ) day
      LEFT JOIN LATERAL (
        SELECT r.id, r.name, r.price
        FROM public.rate_rules r
        WHERE r.unit_id = u.id AND r.active
          AND (r.start_date IS NULL OR day.stay_date >= r.start_date)
          AND (r.end_date IS NULL OR day.stay_date < r.end_date)
          AND (r.day_of_week IS NULL OR r.day_of_week = EXTRACT(DOW FROM day.stay_date) + 1)
        ORDER BY r.priority DESC, (r.day_of_week IS NOT NULL) DESC,
                 ((r.start_date IS NOT NULL)::integer + (r.end_date IS NOT NULL)::integer) DESC,
                 r.updated_at DESC, r.id DESC
        LIMIT 1
      ) rule ON true
      WHERE u.id = ? AND u.status = 'active'
      ORDER BY day.stay_date
      """;

  private static final String BOOKING_JSON =
      """
      (to_jsonb(b) - 'guest_name' - 'guest_email' - 'guest_phone' - 'promotion_id')
      || jsonb_build_object('unit', jsonb_build_object(
           'id', u.id, 'name', u.name, 'slug', u.slug, 'type', u.type,
           'property_id', u.property_id, 'base_price', u.base_price, 'max_guests', u.max_guests,
           'property', jsonb_build_object(
               'id', p.id, 'name', p.name, 'slug', p.slug, 'district', p.district),
           'cover_image', cover.image))
      """;

  private static final String BOOKING_FROM =
      """
      FROM public.bookings b
      JOIN public.units u ON u.id = b.unit_id
      JOIN public.properties p ON p.id = u.property_id
      LEFT JOIN LATERAL (
        SELECT jsonb_build_object('image_url', i.image_url, 'caption', i.caption) AS image
        FROM public.unit_images i
        WHERE i.unit_id = u.id
        ORDER BY i.is_cover DESC, i.display_order, i.id
        LIMIT 1
      ) cover ON true
      WHERE b.customer_id = ?
      """;

  private static final String BOOKING_BREAKDOWN =
      """
      jsonb_build_object(
        'nights', COALESCE((
          SELECT jsonb_agg(jsonb_build_object(
              'stay_date', n.stay_date, 'nightly_rate', n.nightly_rate, 'rate_rule_id', n.rate_rule_id)
              ORDER BY n.stay_date)
          FROM public.booking_nights n
          WHERE n.booking_id = b.id
        ), '[]'::jsonb),
        'price_items', COALESCE((
          SELECT jsonb_agg(jsonb_build_object(
              'item_type', i.item_type, 'label', i.label, 'amount', i.amount,
              'display_order', i.display_order) ORDER BY i.display_order, i.id)
          FROM public.booking_price_items i
          WHERE i.booking_id = b.id
        ), '[]'::jsonb))
      """;

  private final JdbcTemplate jdbc;
  private final ObjectMapper json;

  BookingService(JdbcTemplate jdbc, ObjectMapper json) {
    this.jdbc = jdbc;
    this.json = json;
  }

  public Object availability(Map<String, String> query) {
    long unitId = integer(query.get("unit_id"), 1, Integer.MAX_VALUE, "Căn homestay");
    StayDates stay = StayDates.parse(query.get("check_in"), query.get("check_out"), jdbc);
    List<Boolean> values = jdbc.queryForList(
        """
        SELECT public.is_unit_available(u.id, ?, ?)
        FROM public.units u
        JOIN public.properties p ON p.id = u.property_id AND p.status = 'active'
        WHERE u.id = ? AND u.status = 'active'
        """,
        Boolean.class, stay.checkIn(), stay.checkOut(), unitId);
    if (values.isEmpty()) throw new ApiProblem(404, "Không tìm thấy căn homestay.");
    boolean available = values.get(0);
    return Map.of(
        "unit_id", unitId,
        "check_in", stay.checkIn().toString(),
        "check_out", stay.checkOut().toString(),
        "available", available,
        "reasons", available ? List.of() : List.of("Có booking hoặc lịch chặn trong khoảng ngày này."));
  }

  public Object quote(Map<String, String> query) {
    long unitId = integer(query.get("unit_id"), 1, Integer.MAX_VALUE, "Căn homestay");
    StayDates stay = StayDates.parse(query.get("check_in"), query.get("check_out"), jdbc);
    String guestsValue = query.get("guests");
    Integer guests = guestsValue == null || guestsValue.isBlank()
        ? null : integer(guestsValue, 1, 40, "Số khách");
    String code = optionalText(query.get("promotion_code"), 40, "Mã ưu đãi");
    List<NightRate> rates = jdbc.query(
        NIGHT_RATES,
        (rs, row) -> new NightRate(
            rs.getString("unit_name"), rs.getLong("cleaning_fee"), rs.getInt("max_guests"),
            rs.getObject("stay_date", LocalDate.class), rs.getLong("rate"),
            rs.getObject("rate_rule_id", Long.class), rs.getString("rule_name")),
        stay.checkIn(), stay.nights(), unitId);
    if (rates.isEmpty()) throw new ApiProblem(404, "Không tìm thấy căn homestay.");
    NightRate first = rates.get(0);
    if (guests != null && guests > first.maxGuests()) {
      throw new ApiProblem(422, "Số khách vượt sức chứa của căn này.");
    }
    long subtotal = rates.stream().mapToLong(NightRate::rate).reduce(0, Math::addExact);
    long discount = code == null ? 0 : discount(code, subtotal);

    Map<String, Object> result = new LinkedHashMap<>();
    result.put("unit_id", unitId);
    result.put("unit_name", first.unitName());
    result.put("check_in", stay.checkIn().toString());
    result.put("check_out", stay.checkOut().toString());
    result.put("number_of_nights", stay.nights());
    result.put("guests", guests);
    result.put("nights", rates.stream().map(NightRate::asQuoteRow).toList());
    result.put("subtotal", subtotal);
    result.put("cleaning_fee", first.cleaningFee());
    result.put("discount_amount", discount);
    result.put("total_amount", Math.max(0, Math.addExact(subtotal, first.cleaningFee()) - discount));
    result.put("currency", "VND");
    result.put("promotion_code", code);
    return result;
  }

  @Transactional
  public Object create(long customerId, Map<String, Object> body) {
    long unitId = integer(body.get("unit_id"), 1, Integer.MAX_VALUE, "Căn homestay");
    StayDates stay = StayDates.parse(dateText(body.get("check_in")), dateText(body.get("check_out")), jdbc);
    short adults = (short) integer(body.getOrDefault("adults", 1), 1, 20, "Số người lớn");
    short children = (short) integer(body.getOrDefault("children", 0), 0, 20, "Số trẻ em");
    String requests = optionalText(body.get("special_requests"), 2000, "Yêu cầu đặc biệt");
    String code = optionalText(body.get("promotion_code"), 40, "Mã ưu đãi");
    if (code != null) {
      // The reused RPC locks by unit. Also serialize redemptions of the same code
      // when customers book different units through this Java service.
      jdbc.queryForList("SELECT id FROM public.promotions WHERE code = ? FOR UPDATE", Long.class, code);
    }

    try {
      String payload = jdbc.queryForObject(
          "SELECT public.create_booking_atomic(?, ?, ?, ?, ?, ?, ?, ?)::text",
          String.class, customerId, unitId, stay.checkIn(), stay.checkOut(), adults, children, requests, code);
      return json.readTree(payload);
    } catch (DataAccessException failure) {
      if (failure.getMostSpecificCause() instanceof SQLException sql) {
        String state = sql.getSQLState();
        if (state != null) {
          switch (state) {
            case "YB001" -> throw new ApiProblem(404, "Không tìm thấy căn homestay.");
            case "YB002" -> throw new ApiProblem(422, "Khoảng ngày không hợp lệ.");
            case "YB003" -> throw new ApiProblem(422, "Số khách vượt sức chứa của căn này.");
            case "YB004", "YB005" -> throw new ApiProblem(409,
                "Căn homestay vừa hết chỗ trong khoảng ngày này. Vui lòng chọn ngày khác.");
            case "YB006" -> throw new ApiProblem(422, "Mã ưu đãi không hợp lệ hoặc đã hết hạn.");
            case "YB007" -> throw new ApiProblem(422, "Mã ưu đãi đã hết lượt sử dụng.");
            case "YB008" -> throw new ApiProblem(401, "Phiên đăng nhập không hợp lệ.");
            default -> throw failure;
          }
        }
      }
      throw failure;
    }
  }

  public Object list(long customerId, String status) {
    String query = "SELECT (" + BOOKING_JSON + ")::text " + BOOKING_FROM;
    String order = " ORDER BY b.created_at DESC, b.id DESC LIMIT 100";
    if (status != null && STATUSES.contains(status)) {
      return jdbc.query(query + " AND b.status = ?" + order, (rs, row) -> json.readTree(rs.getString(1)),
          customerId, status);
    }
    return jdbc.query(query + order, (rs, row) -> json.readTree(rs.getString(1)), customerId);
  }

  public Object detail(long customerId, long id) {
    String query = "SELECT (" + BOOKING_JSON + " || " + BOOKING_BREAKDOWN + ")::text " + BOOKING_FROM;
    List<JsonNode> result = jdbc.query(query + " AND b.id = ?", (rs, row) -> json.readTree(rs.getString(1)),
        customerId, id);
    if (result.isEmpty()) throw new ApiProblem(404, "Không tìm thấy đơn đặt của bạn.");
    return result.get(0);
  }

  public Object cancel(long customerId, Map<String, Object> body) {
    long id = positiveId(body.get("id"));
    String reason = optionalText(body.get("reason"), 500, "Lý do hủy");
    int changed = jdbc.update(
        """
        UPDATE public.bookings
        SET status = 'cancelled', cancelled_at = now(), cancellation_reason = ?
        WHERE id = ? AND customer_id = ? AND status IN ('pending', 'confirmed')
        """,
        reason, id, customerId);
    if (changed == 0) {
      List<String> status = jdbc.queryForList(
          "SELECT status FROM public.bookings WHERE id = ? AND customer_id = ?",
          String.class, id, customerId);
      if (status.isEmpty()) throw new ApiProblem(404, "Không tìm thấy đơn đặt của bạn.");
      throw new ApiProblem(422, "Không thể hủy đơn đặt ở trạng thái hiện tại.");
    }
    return Map.of("id", id, "status", "cancelled");
  }

  private long discount(String code, long subtotal) {
    List<Promotion> promotions = jdbc.query(
        """
        SELECT p.discount_type, p.discount_value, p.maximum_discount, p.minimum_booking_value,
               COALESCE(p.usage_limit <= (
                   SELECT count(*) FROM public.promotion_usages u WHERE u.promotion_id = p.id
               ), false) AS exhausted
        FROM public.promotions p
        WHERE p.code = ? AND p.active AND CURRENT_DATE BETWEEN p.valid_from AND p.valid_until
        """,
        (rs, row) -> new Promotion(rs.getString("discount_type"), rs.getLong("discount_value"),
            rs.getObject("maximum_discount", Long.class), rs.getObject("minimum_booking_value", Long.class),
            rs.getBoolean("exhausted")),
        code);
    if (promotions.isEmpty()) throw new ApiProblem(422, "Mã ưu đãi không hợp lệ hoặc đã hết hạn.");
    Promotion promo = promotions.get(0);
    if (promo.minimum() != null && subtotal < promo.minimum()) {
      throw new ApiProblem(422, "Đơn chưa đạt giá trị tối thiểu để dùng mã ưu đãi.");
    }
    if (promo.exhausted()) throw new ApiProblem(422, "Mã ưu đãi đã hết lượt sử dụng.");
    BigDecimal amount = BigDecimal.valueOf(promo.value());
    if ("percentage".equals(promo.type())) {
      amount = BigDecimal.valueOf(subtotal).multiply(amount).movePointLeft(2).setScale(0, RoundingMode.HALF_UP);
    }
    if (promo.cap() != null) amount = amount.min(BigDecimal.valueOf(promo.cap()));
    return amount.longValueExact();
  }

  private static int integer(Object value, int min, int max, String label) {
    try {
      int number = Integer.parseInt(String.valueOf(value));
      if (number >= min && number <= max) return number;
    } catch (NumberFormatException ignored) {
      // The same client-facing validation error covers malformed and out-of-range values.
    }
    throw new ApiProblem(422, label + " không hợp lệ.");
  }

  private static long positiveId(Object value) {
    try {
      long id = Long.parseLong(String.valueOf(value));
      if (id > 0) return id;
    } catch (NumberFormatException ignored) {
      // Handled as a validation error below.
    }
    throw new ApiProblem(422, "Mã đơn đặt không hợp lệ.");
  }

  private static String dateText(Object value) {
    return value instanceof String text ? text : null;
  }

  private static String optionalText(Object value, int limit, String label) {
    if (value == null) return null;
    if (!(value instanceof String raw)) throw new ApiProblem(422, label + " không hợp lệ.");
    String trimmed = raw.strip();
    if (trimmed.codePointCount(0, trimmed.length()) > limit || trimmed.indexOf('\0') >= 0) {
      throw new ApiProblem(422, label + " không hợp lệ.");
    }
    return trimmed.isEmpty() ? null : trimmed;
  }

  private record Promotion(String type, long value, Long cap, Long minimum, boolean exhausted) {}

  private record NightRate(
      String unitName, long cleaningFee, int maxGuests, LocalDate date, long rate, Long ruleId,
      String ruleName) {
    Map<String, Object> asQuoteRow() {
      Map<String, Object> row = new LinkedHashMap<>();
      row.put("date", date.toString());
      row.put("rate", rate);
      row.put("rate_rule_id", ruleId);
      row.put("rule_name", ruleName);
      return row;
    }
  }
}
