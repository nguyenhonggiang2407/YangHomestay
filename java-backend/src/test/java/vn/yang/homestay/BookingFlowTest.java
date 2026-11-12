package vn.yang.homestay;

import static org.assertj.core.api.Assertions.assertThat;

import io.zonky.test.db.postgres.embedded.EmbeddedPostgres;
import java.io.IOException;
import java.net.CookieManager;
import java.net.CookiePolicy;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Duration;
import java.time.LocalDate;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.TimeUnit;
import org.junit.jupiter.api.AfterAll;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.web.server.LocalServerPort;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.DynamicPropertySource;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
class BookingFlowTest {
  private static final ObjectMapper JSON = new ObjectMapper();
  private static final Duration HTTP_TIMEOUT = Duration.ofSeconds(15);
  private static final String TEST_PASSWORD = "Example-Booking-123";
  private static EmbeddedPostgres postgres;

  @LocalServerPort private int port;
  @Autowired private JdbcTemplate jdbc;

  @DynamicPropertySource
  static void database(DynamicPropertyRegistry registry) throws Exception {
    postgres = EmbeddedPostgres.builder().start();
    try {
      initializeDatabase();
    } catch (Exception failure) {
      try {
        closeDatabase();
      } catch (IOException closeFailure) {
        failure.addSuppressed(closeFailure);
      }
      throw failure;
    }
    registry.add("spring.datasource.url", () -> postgres.getJdbcUrl("postgres", "postgres"));
    registry.add("spring.datasource.username", () -> "postgres");
    registry.add("spring.datasource.password", () -> "");
    registry.add("server.servlet.session.cookie.secure", () -> false);
  }

  private static void initializeDatabase() throws Exception {
    Path directory = Path.of("..", "database", "supabase");
    try (var connection = postgres.getPostgresDatabase().getConnection();
        var statement = connection.createStatement();
        var paths = Files.list(directory)) {
      // These Supabase role names are required by the existing grants migration.
      statement.execute("CREATE ROLE anon NOLOGIN; CREATE ROLE authenticated NOLOGIN; CREATE ROLE service_role NOLOGIN;");
      for (Path migration : paths
          .filter(path -> path.getFileName().toString().matches("[0-9]{3}_.*\\.sql"))
          .sorted()
          .toList()) {
        statement.execute(Files.readString(migration));
      }
    }
  }

  @AfterAll
  static void closeDatabase() throws IOException {
    if (postgres != null) postgres.close();
  }

  @Test
  void unauthenticatedBookingAndMissingCsrfAreRejected() throws Exception {
    CustomerSession guest = new CustomerSession();
    assertThat(guest.get("my-bookings", Map.of()).statusCode()).isEqualTo(401);
    assertThat(guest.get("check-auth", Map.of()).body().path("authenticated").asBoolean()).isFalse();
    Result noToken = guest.post(
        "login", Map.of("email", "guest@example.test", "password", TEST_PASSWORD), false);
    assertThat(noToken.statusCode()).isEqualTo(419);
    assertThat(noToken.body().path("success").asBoolean()).isFalse();
  }

  @Test
  void nightRatesAndOwnershipRemainCorrectAfterPricesChange() throws Exception {
    CustomerSession owner = customer("owner");
    CustomerSession stranger = customer("stranger");
    LocalDate arrival = futureDate(365);
    LocalDate departure = arrival.plusDays(2);
    long unitId = 1;
    int weekday = arrival.getDayOfWeek().getValue() % 7 + 1;
    jdbc.update(
        """
        INSERT INTO public.rate_rules(unit_id, name, start_date, end_date, price, priority)
        VALUES (?, 'Stay dates', ?, ?, 200000, 32000)
        """, unitId, arrival, departure);
    long preferredRule = jdbc.queryForObject(
        """
        INSERT INTO public.rate_rules(unit_id, name, day_of_week, price, priority)
        VALUES (?, 'Specific weekday', ?, 345001, 32000)
        RETURNING id
        """, Long.class, unitId, weekday);

    Map<String, String> stay = stayQuery(unitId, arrival, departure);
    assertThat(owner.get("availability", stay).data(200).path("available").asBoolean()).isTrue();
    JsonNode quote = owner.get("booking-quote", stay).data(200);
    assertThat(quote.path("number_of_nights").asInt()).isEqualTo(2);
    assertThat(quote.path("subtotal").asLong()).isEqualTo(545001);
    assertThat(quote.path("nights").path(0).path("rate_rule_id").asLong()).isEqualTo(preferredRule);

    Map<String, Object> request = booking(unitId, arrival, departure);
    request.put("total_amount", 1);
    JsonNode created = owner.post("create-booking", request).data(201);
    long bookingId = created.path("id").asLong();
    assertThat(bookingId).isPositive();
    assertThat(created.path("total_amount").asLong()).isEqualTo(quote.path("total_amount").asLong());
    assertThat(created.path("status").asString()).isEqualTo("pending");
    assertThat(owner.get("availability", stay).data(200).path("available").asBoolean()).isFalse();

    jdbc.update("UPDATE public.rate_rules SET price = 111111 WHERE id = ?", preferredRule);
    Map<String, String> lookup = Map.of("id", Long.toString(bookingId));
    JsonNode detail = owner.get("booking-detail", lookup).data(200);
    assertThat(detail.path("nights").path(0).path("nightly_rate").asLong()).isEqualTo(345001);
    assertThat(detail.path("total_amount").asLong()).isEqualTo(created.path("total_amount").asLong());
    assertThat(detail.path("unit").path("cover_image").path("image_url").asString()).isNotBlank();
    JsonNode history = owner.get("my-bookings", Map.of()).data(200);
    assertThat(history.size()).isEqualTo(1);
    assertThat(history.path(0).path("id").asLong()).isEqualTo(bookingId);

    assertThat(stranger.get("my-bookings", Map.of()).data(200).size()).isZero();
    assertThat(stranger.get("booking-detail", lookup).statusCode()).isEqualTo(404);
    assertThat(stranger.post("cancel-booking", Map.of("id", bookingId)).statusCode()).isEqualTo(404);
    assertThat(owner.post("cancel-booking", Map.of("id", bookingId)).data(200)
        .path("status").asString()).isEqualTo("cancelled");
    assertThat(owner.get("availability", stay).data(200).path("available").asBoolean()).isTrue();
  }

  @Test
  void competingRequestsCreateOneBookingAndAdjacentStaysAreAllowed() throws Exception {
    CustomerSession first = customer("race-first");
    CustomerSession second = customer("race-second");
    LocalDate arrival = futureDate(400);
    LocalDate checkout = arrival.plusDays(2);
    long unitId = 2;

    HttpRequest firstRequest = first.postRequest("create-booking", booking(unitId, arrival, checkout), true);
    HttpRequest secondRequest = second.postRequest("create-booking", booking(unitId, arrival, checkout), true);
    var a = first.client.sendAsync(firstRequest, HttpResponse.BodyHandlers.ofString());
    var b = second.client.sendAsync(secondRequest, HttpResponse.BodyHandlers.ofString());
    assertThat(List.of(a.get(20, TimeUnit.SECONDS).statusCode(), b.get(20, TimeUnit.SECONDS).statusCode()))
        .containsExactlyInAnyOrder(201, 409);
    assertThat(jdbc.queryForObject(
        "SELECT count(*) FROM public.bookings WHERE unit_id = ? AND check_in = ? AND check_out = ?",
        Long.class, unitId, arrival, checkout)).isEqualTo(1L);

    assertThat(second.post("create-booking", booking(unitId, checkout, checkout.plusDays(2)))
        .data(201).path("check_in").asString()).isEqualTo(checkout.toString());
  }

  @Test
  void promotionUsageLimitHoldsAcrossConcurrentBookingsForDifferentUnits() throws Exception {
    CustomerSession first = customer("promo-first");
    CustomerSession second = customer("promo-second");
    LocalDate arrival = futureDate(430);
    LocalDate checkout = arrival.plusDays(1);
    String code = "BOOKING-TEST-ONLY";
    long promotion = jdbc.queryForObject(
        """
        INSERT INTO public.promotions(
            code, name, discount_type, discount_value, maximum_discount, valid_from, valid_until, usage_limit)
        VALUES (?, 'Test promotion', 'percentage', 10, 5000, CURRENT_DATE - 1, CURRENT_DATE + 1, 1)
        RETURNING id
        """, Long.class, code);
    Map<String, String> quote = new LinkedHashMap<>(stayQuery(4, arrival, checkout));
    quote.put("promotion_code", code);
    assertThat(first.get("booking-quote", quote).data(200).path("discount_amount").asLong()).isEqualTo(5000);

    Map<String, Object> firstBody = booking(4, arrival, checkout);
    Map<String, Object> secondBody = booking(5, arrival, checkout);
    firstBody.put("promotion_code", code);
    secondBody.put("promotion_code", code);
    HttpRequest firstRequest = first.postRequest("create-booking", firstBody, true);
    HttpRequest secondRequest = second.postRequest("create-booking", secondBody, true);
    var a = first.client.sendAsync(firstRequest, HttpResponse.BodyHandlers.ofString());
    var b = second.client.sendAsync(secondRequest, HttpResponse.BodyHandlers.ofString());
    assertThat(List.of(a.get(20, TimeUnit.SECONDS).statusCode(), b.get(20, TimeUnit.SECONDS).statusCode()))
        .containsExactlyInAnyOrder(201, 422);
    assertThat(jdbc.queryForObject(
        "SELECT count(*) FROM public.promotion_usages WHERE promotion_id = ?", Long.class, promotion))
        .isEqualTo(1L);
  }

  private CustomerSession customer(String suffix) throws Exception {
    CustomerSession session = new CustomerSession();
    String email = suffix + "@example.test";
    session.post("register", Map.of(
        "full_name", "Booking test " + suffix,
        "email", email,
        "phone", "0900000101",
        "password", TEST_PASSWORD,
        "confirm_password", TEST_PASSWORD)).data(201);
    session.post("login", Map.of("email", email, "password", TEST_PASSWORD)).data(200);
    assertThat(session.get("check-auth", Map.of()).body().path("authenticated").asBoolean()).isTrue();
    return session;
  }

  private LocalDate futureDate(int days) {
    return jdbc.queryForObject("SELECT CURRENT_DATE", LocalDate.class).plusDays(days);
  }

  private static Map<String, String> stayQuery(long unitId, LocalDate arrival, LocalDate checkout) {
    return Map.of("unit_id", Long.toString(unitId), "check_in", arrival.toString(), "check_out", checkout.toString());
  }

  private static Map<String, Object> booking(long unitId, LocalDate arrival, LocalDate checkout) {
    return new LinkedHashMap<>(Map.of(
        "unit_id", unitId, "check_in", arrival.toString(), "check_out", checkout.toString(), "adults", 1));
  }

  private URI uri(String action, Map<String, String> query) {
    StringBuilder url = new StringBuilder("http://127.0.0.1:").append(port)
        .append("/backend/customer-api.php?action=").append(action);
    query.forEach((key, value) -> url.append('&').append(key).append('=')
        .append(URLEncoder.encode(value, StandardCharsets.UTF_8)));
    return URI.create(url.toString());
  }

  private final class CustomerSession {
    private final HttpClient client = HttpClient.newBuilder()
        .cookieHandler(new CookieManager(null, CookiePolicy.ACCEPT_ORIGINAL_SERVER))
        .connectTimeout(HTTP_TIMEOUT)
        .build();

    Result get(String action, Map<String, String> query) throws Exception {
      return send(HttpRequest.newBuilder(uri(action, query)).timeout(HTTP_TIMEOUT).GET().build());
    }

    Result post(String action, Map<String, Object> body) throws Exception {
      return post(action, body, true);
    }

    Result post(String action, Map<String, Object> body, boolean csrf) throws Exception {
      return send(postRequest(action, body, csrf));
    }

    HttpRequest postRequest(String action, Map<String, Object> body, boolean csrf) throws Exception {
      var request = HttpRequest.newBuilder(uri(action, Map.of()))
          .timeout(HTTP_TIMEOUT)
          .header("Content-Type", "application/json")
          .POST(HttpRequest.BodyPublishers.ofString(JSON.writeValueAsString(body)));
      if (csrf) {
        String token = get("get-csrf", Map.of()).data(200).path("token").asString();
        assertThat(token).isNotBlank();
        request.header("X-CSRF-Token", token);
      }
      return request.build();
    }

    private Result send(HttpRequest request) throws Exception {
      var response = client.send(request, HttpResponse.BodyHandlers.ofString());
      return new Result(response.statusCode(), JSON.readTree(response.body()));
    }
  }

  private record Result(int statusCode, JsonNode body) {
    JsonNode data(int expectedStatus) {
      assertThat(statusCode).as("Response: %s", body).isEqualTo(expectedStatus);
      assertThat(body.path("success").asBoolean()).as("Response: %s", body).isTrue();
      return body.path("data");
    }
  }
}
