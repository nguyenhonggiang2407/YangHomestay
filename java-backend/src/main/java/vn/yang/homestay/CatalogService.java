package vn.yang.homestay;

import java.util.List;
import java.util.Map;
import java.util.Set;
import org.springframework.jdbc.core.namedparam.MapSqlParameterSource;
import org.springframework.jdbc.core.namedparam.NamedParameterJdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;
import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.ObjectMapper;

@Service
@Transactional(readOnly = true, isolation = Isolation.REPEATABLE_READ)
public class CatalogService {
  private static final Set<String> UNIT_TYPES =
      Set.of("studio", "apartment", "loft", "couple", "family", "villa", "house");
  private static final TypeReference<Map<String, Object>> JSON_OBJECT = new TypeReference<>() {};
  private static final String ACTIVE_PROPERTIES = " from public.properties p where p.status = 'active'";
  private static final String ACTIVE_UNITS = """
       from public.units u
       join public.properties p on p.id = u.property_id
       left join public.v_unit_rating r on r.unit_id = u.id
       where p.status = 'active' and u.status = 'active'
      """;
  private static final String UNIT_FIELDS = """
        'property', to_jsonb(p),
        'avg_rating', coalesce(r.avg_rating, 0),
        'review_count', coalesce(r.review_count, 0),
        'cover_image', (
          select jsonb_build_object(
            'unit_id', i.unit_id, 'image_url', i.image_url, 'caption', i.caption,
            'image_category', i.image_category, 'display_order', i.display_order,
            'is_cover', i.is_cover
          )
          from public.unit_images i where i.unit_id = u.id
          order by i.is_cover desc, i.display_order, i.id limit 1
        )
      """;
  private static final String UNIT_GALLERY = """
      , 'gallery', (
        select coalesce(jsonb_agg(
          jsonb_build_object(
            'image_url', gallery.image_url, 'caption', gallery.caption,
            'image_category', gallery.image_category, 'display_order', gallery.display_order,
            'is_cover', gallery.is_cover
          ) order by gallery.display_order, gallery.id
        ), '[]'::jsonb)
        from (
          select i.id, i.image_url, i.caption, i.image_category, i.display_order, i.is_cover
          from public.unit_images i where i.unit_id = u.id
          order by i.display_order, i.id limit 50
        ) gallery
      )
      """;
  private static final String UNIT = unitProjection(UNIT_FIELDS);
  private static final String UNIT_WITH_GALLERY = unitProjection(UNIT_FIELDS + UNIT_GALLERY);
  private static final String PROPERTY = """
      select (to_jsonb(p) || jsonb_build_object(
        'cover_image', (
          select jsonb_build_object(
            'property_id', i.property_id, 'image_url', i.image_url, 'caption', i.caption,
            'image_category', i.image_category, 'display_order', i.display_order,
            'is_cover', i.is_cover
          )
          from public.property_images i where i.property_id = p.id
          order by i.is_cover desc, i.display_order, i.id limit 1
        ),
        'gallery', (
          select coalesce(
            jsonb_agg(to_jsonb(gallery) - 'id' order by gallery.display_order, gallery.id),
            '[]'::jsonb
          )
          from (
            select i.id, i.property_id, i.image_url, i.caption, i.image_category,
                   i.display_order, i.is_cover
            from public.property_images i where i.property_id = p.id
            order by i.display_order, i.id limit 50
          ) gallery
        ),
        'unit_count', (
          select count(*) from public.units u where u.property_id = p.id and u.status = 'active'
        )
      ))::text
      """;

  private final NamedParameterJdbcTemplate jdbc;
  private final ObjectMapper json;

  CatalogService(NamedParameterJdbcTemplate jdbc, ObjectMapper json) {
    this.jdbc = jdbc;
    this.json = json;
  }

  public Object units(Map<String, String> query) {
    var where = new StringBuilder(ACTIVE_UNITS);
    var args = new MapSqlParameterSource();
    String property = value(query, "property");
    if (!property.isEmpty()) {
      where.append(" and p.id = :propertyId");
      args.addValue("propertyId", number(property, "Mã homestay không hợp lệ."));
    }
    String type = value(query, "type");
    if (UNIT_TYPES.contains(type)) {
      where.append(" and u.type = :type");
      args.addValue("type", type);
    }
    Long min = price(query, "min_price");
    Long max = price(query, "max_price");
    if (min != null && max != null && min > max) {
      throw new ApiProblem(422, "Giá tối thiểu không được lớn hơn giá tối đa.");
    }
    if (min != null) {
      where.append(" and u.base_price >= :minPrice");
      args.addValue("minPrice", min);
    }
    if (max != null) {
      where.append(" and u.base_price <= :maxPrice");
      args.addValue("maxPrice", max);
    }
    String guests = value(query, "guests");
    if (!guests.isEmpty()) {
      long requested = number(guests, "Số khách không hợp lệ.");
      if (requested < 1 || requested > 20) {
        throw new ApiProblem(422, "Số khách không hợp lệ.");
      }
      where.append(" and u.max_guests >= :guests");
      args.addValue("guests", requested);
    }
    String tag = value(query, "tag");
    if (!tag.isEmpty()) {
      where.append("""
           and exists (
             select 1 from public.unit_experience_tags ut
             join public.experience_tags t on t.id = ut.tag_id
             where ut.unit_id = u.id and t.code = :tag and t.active
           )
          """);
      args.addValue("tag", tag);
    }
    String checkIn = value(query, "check_in");
    String checkOut = value(query, "check_out");
    if (!checkIn.isEmpty() || !checkOut.isEmpty()) {
      StayDates dates = StayDates.parse(checkIn, checkOut, jdbc.getJdbcTemplate());
      where.append(" and u.id in (select unit_id from public.available_units(:checkIn, :checkOut))");
      args.addValue("checkIn", dates.checkIn()).addValue("checkOut", dates.checkOut());
    }
    appendSharedFilters(where, args, query, "u");
    String sort = switch (value(query, "sort")) {
      case "price_desc" -> "u.base_price desc, u.id";
      case "newest" -> "u.created_at desc, u.id";
      case "rating" -> "coalesce(r.avg_rating, 0) desc, u.id";
      default -> "u.base_price, u.id";
    };
    return page(UNIT, where, sort, args, query);
  }

  public Object unit(long id) {
    Map<String, Object> unit = one(
        UNIT_WITH_GALLERY + ACTIVE_UNITS + " and u.id = :id", id, "Không tìm thấy căn homestay.");
    long propertyId = ((Number) unit.get("property_id")).longValue();
    unit.put("nearby_places", nearby(propertyId));
    unit.put("amenities", jdbc.queryForList("""
        select a.id, a.code, a.name_vi, a.name_en, a.icon, a.category
        from public.unit_amenities ua join public.amenities a on a.id = ua.amenity_id
        where ua.unit_id = :id and a.active order by a.id limit 50
        """, Map.of("id", id)));
    unit.put("experience_tags", jdbc.queryForList("""
        select t.id, t.code, t.name_vi, t.name_en, t.category, ut.weight
        from public.unit_experience_tags ut join public.experience_tags t on t.id = ut.tag_id
        where ut.unit_id = :id and t.active order by ut.weight desc, t.id limit 30
        """, Map.of("id", id)));
    unit.put("reviews", objects("""
        select jsonb_build_object(
          'id', rv.id, 'unit_id', rv.unit_id, 'overall_rating', rv.overall_rating,
          'cleanliness_rating', rv.cleanliness_rating, 'location_rating', rv.location_rating,
          'amenities_rating', rv.amenities_rating, 'accuracy_rating', rv.accuracy_rating,
          'communication_rating', rv.communication_rating, 'value_rating', rv.value_rating,
          'title', rv.title, 'comment', rv.comment, 'status', rv.status, 'created_at', rv.created_at,
          'customer', jsonb_build_object('full_name', c.full_name, 'avatar_url', c.avatar_url),
          'verified_stay', b.id is not null,
          'stay_check_in', b.check_in, 'stay_check_out', b.check_out
        )::text
        from public.reviews rv
        join public.customers c on c.id = rv.customer_id
        left join public.bookings b on b.id = rv.booking_id
          and b.customer_id = rv.customer_id and b.unit_id = rv.unit_id and b.status = 'checkout'
        where rv.unit_id = :id and rv.status = 'approved'
        order by rv.created_at desc, rv.id desc limit 50
        """, new MapSqlParameterSource("id", id)));
    return unit;
  }

  public Object properties(Map<String, String> query) {
    var where = new StringBuilder(ACTIVE_PROPERTIES);
    var args = new MapSqlParameterSource();
    appendSharedFilters(where, args, query, "p");
    String sort = switch (value(query, "sort")) {
      case "name_asc" -> "p.name, p.id";
      case "name_desc" -> "p.name desc, p.id";
      case "newest" -> "p.created_at desc, p.id";
      default -> "p.featured desc, p.created_at desc, p.id";
    };
    return page(PROPERTY, where, sort, args, query);
  }

  public Object property(long id) {
    Map<String, Object> property = one(
        PROPERTY + ACTIVE_PROPERTIES + " and p.id = :id", id, "Không tìm thấy homestay.");
    property.put("nearby_places", nearby(id));
    return Map.of("property", property, "units", unitsByProperty(id));
  }

  public Object unitsByProperty(long id) {
    return objects(
        UNIT_WITH_GALLERY + ACTIVE_UNITS + " and p.id = :id order by u.base_price, u.id limit 50",
        new MapSqlParameterSource("id", id));
  }

  private static String unitProjection(String fields) {
    return "select (to_jsonb(u) || jsonb_build_object(" + fields + "))::text";
  }

  private List<Map<String, Object>> nearby(long propertyId) {
    return jdbc.queryForList("""
        select id, name, category, short_description, distance_meters, walking_minutes,
               image_url, featured
        from public.nearby_places where property_id = :id order by distance_meters, id limit 50
        """, Map.of("id", propertyId));
  }

  private Map<String, Object> one(String sql, long id, String notFound) {
    List<Map<String, Object>> rows = objects(sql, new MapSqlParameterSource("id", id));
    if (rows.isEmpty()) {
      throw new ApiProblem(404, notFound);
    }
    return rows.get(0);
  }

  private List<Map<String, Object>> objects(String sql, MapSqlParameterSource args) {
    return jdbc.query(sql, args, (row, index) -> json.readValue(row.getString(1), JSON_OBJECT));
  }

  private Map<String, Object> page(
      String select, StringBuilder where, String order, MapSqlParameterSource args,
      Map<String, String> query) {
    int size = (int) Math.min(50, Math.max(1, parameter(query, "limit", 12)));
    long page = Math.min(Integer.MAX_VALUE, Math.max(1, parameter(query, "page", 1)));
    long total = jdbc.queryForObject("select count(*)" + where, args, Long.class);
    args.addValue("limit", size).addValue("offset", (page - 1) * size);
    return Map.of(
        "items", objects(select + where + " order by " + order + " limit :limit offset :offset", args),
        "pagination", Map.of("page", page, "limit", size, "total", total,
            "total_pages", total / size + (total % size == 0 ? 0 : 1)));
  }

  private static void appendSharedFilters(
      StringBuilder where, MapSqlParameterSource args, Map<String, String> query, String table) {
    String district = value(query, "district");
    if (!district.isEmpty()) {
      where.append(" and lower(p.district) = lower(:district)");
      args.addValue("district", district);
    }
    String featured = value(query, "featured");
    if (!featured.isEmpty() && !"false".equalsIgnoreCase(featured) && !"0".equals(featured)) {
      where.append(" and ").append(table).append(".featured");
    }
    String search = value(query, "search");
    if (!search.isEmpty()) {
      String fields = "u".equals(table) ? "u.name, p.name, p.district, u.slug" : "p.name, p.district, p.slug";
      where.append(" and strpos(lower(concat_ws(' ', ").append(fields).append(")), lower(:search)) > 0");
      args.addValue("search", search);
    }
  }

  private static Long price(Map<String, String> query, String key) {
    String value = value(query, key);
    if (value.isEmpty()) {
      return null;
    }
    long amount = number(value, "Khoảng giá không hợp lệ.");
    if (amount < 0) {
      throw new ApiProblem(422, "Khoảng giá không hợp lệ.");
    }
    return amount;
  }

  private static long parameter(Map<String, String> query, String key, long fallback) {
    String value = value(query, key);
    return value.isEmpty() ? fallback : number(value, "Thông số phân trang không hợp lệ.");
  }

  private static long number(String value, String message) {
    try {
      return Long.parseLong(value);
    } catch (NumberFormatException invalidNumber) {
      throw new ApiProblem(422, message);
    }
  }

  private static String value(Map<String, String> query, String key) {
    return query.getOrDefault(key, "").strip();
  }
}
