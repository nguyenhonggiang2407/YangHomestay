package vn.yang.homestay;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

@Service
class YangMatchService {
  private static final Map<String, Integer> MINIMUM_GUESTS =
      Map.of("solo", 1, "couple", 2, "friends", 3, "family", 3, "business", 1);
  private static final Set<String> STYLES =
      Set.of("quiet", "romantic", "vintage", "modern", "vibrant", "nature");
  private static final Set<String> AMENITIES = Set.of(
      "bathtub", "balcony", "lake_view", "kitchen", "smart_tv", "workspace",
      "washing_machine", "elevator", "parking");
  private static final String CANDIDATES = """
      select u.id, u.base_price, coalesce(r.avg_rating, 0) as avg_rating,
        coalesce((
          select jsonb_object_agg(t.code, jsonb_build_object('name', t.name_vi, 'weight', ut.weight))
          from public.unit_experience_tags ut
          join public.experience_tags t on t.id = ut.tag_id and t.active
          where ut.unit_id = u.id
        ), '{}'::jsonb)::text as tags,
        coalesce((
          select jsonb_object_agg(a.code, a.name_vi)
          from public.unit_amenities ua
          join public.amenities a on a.id = ua.amenity_id and a.active
          where ua.unit_id = u.id
        ), '{}'::jsonb)::text as amenities
      from public.units u
      join public.properties p on p.id = u.property_id and p.status = 'active'
      left join public.v_unit_rating r on r.unit_id = u.id
      where u.status = 'active' and u.max_guests >= ?
      """;

  private final JdbcTemplate jdbc;
  private final ObjectMapper json;
  private final CatalogService catalog;
  private final BookingService bookings;

  YangMatchService(JdbcTemplate jdbc, ObjectMapper json, CatalogService catalog, BookingService bookings) {
    this.jdbc = jdbc;
    this.json = json;
    this.catalog = catalog;
    this.bookings = bookings;
  }

  @Transactional(readOnly = true, isolation = Isolation.REPEATABLE_READ)
  public Object match(Map<String, Object> input) {
    String companion = choice(input.get("q1"), MINIMUM_GUESTS.keySet(), "couple", "Người đi cùng");
    String style = choice(input.get("q2"), STYLES, "quiet", "Phong cách");
    List<String> amenities = amenities(input.get("q3"));
    long budget = integer(input.getOrDefault("q4", 0), 0, 1_000_000_000_000L, "Ngân sách");
    int minimumGuests = MINIMUM_GUESTS.get(companion);
    int guests = (int) integer(input.getOrDefault("guests", minimumGuests),
        minimumGuests, 20, "Số khách");
    String checkIn = dateText(input.get("check_in"));
    String checkOut = dateText(input.get("check_out"));
    StayDates stay = checkIn.isEmpty() && checkOut.isEmpty()
        ? null : StayDates.parse(checkIn, checkOut, jdbc);

    List<Object> arguments = new ArrayList<>();
    arguments.add(guests);
    String sql = CANDIDATES;
    if (stay != null) {
      sql += " and public.is_unit_available(u.id, ?, ?)";
      arguments.add(stay.checkIn());
      arguments.add(stay.checkOut());
    }
    sql += " order by u.id";
    List<Candidate> candidates = jdbc.query(sql, (row, index) -> new Candidate(
        row.getLong("id"), row.getLong("base_price"), row.getDouble("avg_rating"),
        json.readTree(row.getString("tags")), json.readTree(row.getString("amenities"))),
        arguments.toArray());
    List<RankedStay> matches = new ArrayList<>();
    for (Candidate candidate : candidates) {
      Map<String, Object> quote = stay == null ? null : quote(candidate.id(), stay, guests);
      long nightlySubtotal = quote == null ? candidate.basePrice() : ((Number) quote.get("subtotal")).longValue();
      long nightlyBudget = stay == null ? budget : budget * stay.nights();
      if (budget > 0 && nightlySubtotal > nightlyBudget) continue;
      long cost = quote == null ? candidate.basePrice() : ((Number) quote.get("total_amount")).longValue();
      matches.add(rank(candidate, companion, style, amenities, guests, budget, stay, quote, cost));
    }
    matches.sort(Comparator.comparingDouble(RankedStay::score).reversed()
        .thenComparingLong(RankedStay::cost)
        .thenComparingLong(RankedStay::id));

    Map<String, Object> answers = new LinkedHashMap<>();
    answers.put("q1", companion);
    answers.put("q2", style);
    answers.put("q3", amenities);
    answers.put("q4", budget);
    answers.put("minimum_guests", minimumGuests);
    answers.put("guests", guests);
    if (stay != null) {
      answers.put("check_in", stay.checkIn().toString());
      answers.put("check_out", stay.checkOut().toString());
    }
    return Map.of(
        "answers", answers,
        "matches", matches.stream().limit(3).map(this::result).toList(),
        "pricing_basis", stay == null ? "base" : "stay");
  }

  @SuppressWarnings("unchecked")
  private Map<String, Object> quote(long unitId, StayDates stay, int guests) {
    return (Map<String, Object>) bookings.quote(Map.of(
        "unit_id", Long.toString(unitId),
        "check_in", stay.checkIn().toString(),
        "check_out", stay.checkOut().toString(),
        "guests", Integer.toString(guests)));
  }

  private RankedStay rank(Candidate unit, String companion, String style, List<String> amenities,
      int guests, long budget, StayDates stay, Map<String, Object> quote, long cost) {
    List<Map<String, String>> reasons = new ArrayList<>();
    reasons.add(Map.of("text", "Đủ sức chứa cho " + guests + " khách"));
    double score = tagScore(unit.tags(), companion, 3, reasons);
    if (style.equals("vibrant")) {
      score += tagScore(unit.tags(), "nightlife", 2, reasons);
      score += tagScore(unit.tags(), "food-lover", 2, reasons);
    } else {
      score += tagScore(unit.tags(), style.equals("nature") ? "lake-view" : style, 2.5, reasons);
    }
    for (String amenity : amenities) {
      if (unit.amenities().has(amenity)) {
        score += 3;
        reasons.add(Map.of("text", "Có " + unit.amenities().path(amenity).asString()));
      }
    }
    if (budget > 0) {
      score += 6;
      reasons.add(Map.of("text", stay == null
          ? "Giá cơ bản trong ngân sách mỗi đêm"
          : "Giá phòng bình quân trong ngân sách, chưa gồm phí vệ sinh"));
    }
    if (stay != null) reasons.add(Map.of("text", "Còn trống trong " + stay.nights() + " đêm đã chọn"));
    score += unit.rating() * 2;
    double rounded = BigDecimal.valueOf(score).setScale(1, RoundingMode.HALF_UP).doubleValue();
    return new RankedStay(unit.id(), rounded, cost, reasons, quote);
  }

  private static double tagScore(JsonNode tags, String code, double multiplier,
      List<Map<String, String>> reasons) {
    JsonNode tag = tags.path(code);
    if (tag.isMissingNode()) return 0;
    int weight = tag.path("weight").asInt();
    reasons.add(Map.of("text", tag.path("name").asString() + " (mức " + weight + "/10)"));
    return weight * multiplier;
  }

  private Map<String, Object> result(RankedStay match) {
    Map<String, Object> result = new LinkedHashMap<>();
    result.put("unit", catalog.unit(match.id()));
    result.put("score_raw", match.score());
    result.put("score_pct", Math.min(99, Math.round(match.score())));
    result.put("reasons", match.reasons());
    if (match.quote() != null) result.put("quote", match.quote());
    return result;
  }

  private static String choice(Object value, Set<String> allowed, String fallback, String field) {
    if (value == null) return fallback;
    if (value instanceof String selected && allowed.contains(selected)) return selected;
    throw new ApiProblem(422, field + " không hợp lệ.");
  }

  private static List<String> amenities(Object value) {
    if (value == null) return List.of();
    if (!(value instanceof List<?> requested)) throw new ApiProblem(422, "Tiện nghi không hợp lệ.");
    Set<String> unique = new LinkedHashSet<>();
    for (Object item : requested) {
      if (!(item instanceof String code) || !AMENITIES.contains(code)) {
        throw new ApiProblem(422, "Tiện nghi không hợp lệ.");
      }
      unique.add(code);
    }
    return List.copyOf(unique);
  }

  private static long integer(Object value, long minimum, long maximum, String field) {
    if (value instanceof Number || value instanceof String) {
      String text = value.toString();
      if (text.matches("[0-9]{1,13}")) {
        long number = Long.parseLong(text);
        if (number >= minimum && number <= maximum) return number;
      }
    }
    throw new ApiProblem(422, field + " không hợp lệ.");
  }

  private static String dateText(Object value) {
    if (value == null) return "";
    if (value instanceof String text) return text;
    throw new ApiProblem(422, "Ngày lưu trú không hợp lệ.");
  }

  private record Candidate(long id, long basePrice, double rating, JsonNode tags, JsonNode amenities) {}

  private record RankedStay(long id, double score, long cost, List<Map<String, String>> reasons,
      Map<String, Object> quote) {}
}
