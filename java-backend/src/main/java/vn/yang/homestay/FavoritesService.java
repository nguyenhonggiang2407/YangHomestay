package vn.yang.homestay;

import java.util.List;
import java.util.Map;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.ObjectMapper;

@Service
class FavoritesService {
  private static final TypeReference<Map<String, Object>> JSON_OBJECT = new TypeReference<>() {};
  private final JdbcTemplate jdbc;
  private final ObjectMapper json;

  FavoritesService(JdbcTemplate jdbc, ObjectMapper json) {
    this.jdbc = jdbc;
    this.json = json;
  }

  List<Map<String, Object>> list(long customerId) {
    return jdbc.query("""
        select jsonb_build_object(
          'id', f.id, 'unit_id', f.unit_id, 'created_at', f.created_at,
          'unit', jsonb_build_object(
            'id', u.id, 'name', u.name, 'slug', u.slug, 'type', u.type,
            'base_price', u.base_price, 'cleaning_fee', u.cleaning_fee,
            'max_guests', u.max_guests, 'bedrooms', u.bedrooms, 'beds', u.beds,
            'bathrooms', u.bathrooms, 'area_m2', u.area_m2,
            'short_description', u.short_description, 'property_id', u.property_id,
            'featured', u.featured,
            'property', jsonb_build_object(
              'id', p.id, 'name', p.name, 'slug', p.slug, 'district', p.district
            ),
            'cover_image', (
              select jsonb_build_object(
                'unit_id', i.unit_id, 'image_url', i.image_url, 'caption', i.caption,
                'is_cover', i.is_cover, 'display_order', i.display_order
              )
              from public.unit_images i where i.unit_id = u.id
              order by i.is_cover desc, i.display_order, i.id limit 1
            )
          )
        )::text
        from public.favorites f
        join public.units u on u.id = f.unit_id and u.status = 'active'
        join public.properties p on p.id = u.property_id and p.status = 'active'
        where f.customer_id = ?
        order by f.created_at desc, f.id desc limit 100
        """, (row, index) -> json.readValue(row.getString(1), JSON_OBJECT), customerId);
  }

  boolean add(long customerId, long unitId) {
    Boolean inserted = jdbc.queryForObject("""
        with active_unit as (
          select u.id from public.units u
          join public.properties p on p.id = u.property_id
          where u.id = ? and u.status = 'active' and p.status = 'active'
        ), saved as (
          insert into public.favorites (customer_id, unit_id)
          select ?, id from active_unit
          on conflict (customer_id, unit_id) do nothing
          returning id
        )
        select case when exists (select 1 from active_unit)
          then exists (select 1 from saved) end
        """, Boolean.class, unitId, customerId);
    if (inserted == null) {
      throw new ApiProblem(404, "Không tìm thấy căn homestay.");
    }
    return inserted;
  }

  void remove(long customerId, long unitId) {
    jdbc.update("delete from public.favorites where customer_id = ? and unit_id = ?",
        customerId, unitId);
  }
}
