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
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.test.context.DynamicPropertyRegistry;
import org.springframework.test.context.DynamicPropertySource;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

@SpringBootTest(
    webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT,
    properties = "yanghomestay.reception.enabled=true")
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

  @Test
  void suggestsThreeNearestStaysWithoutChangingTheNumberOfNights() throws Exception {
    CustomerSession guest = new CustomerSession();
    CustomerSession owner = customer("suggestion-owner");
    long unitId = 3;
    LocalDate arrival = futureDate(500);
    var stay = stayQuery(unitId, arrival, arrival.plusDays(2));
    var open = guest.get("availability", stay).data(200);
    assertThat(open.path("available").asBoolean()).isTrue();
    assertThat(open.path("suggestions").size()).isZero();

    jdbc.update("insert into availability_blocks (unit_id, start_date, end_date) values (?, ?, ?)",
        unitId, arrival, arrival.plusDays(2));
    owner.post("create-booking", booking(unitId, arrival.plusDays(4), arrival.plusDays(6))).data(201);

    var unavailable = guest.get("availability", stay).data(200);
    assertThat(unavailable.path("available").asBoolean()).isFalse();
    assertThat(unavailable.path("suggestions").size()).isEqualTo(3);
    int index = 0;
    for (int offset : List.of(2, 6, 7)) {
      var option = unavailable.path("suggestions").path(index++);
      assertThat(option.path("check_in").asString()).isEqualTo(arrival.plusDays(offset).toString());
      assertThat(option.path("check_out").asString()).isEqualTo(arrival.plusDays(offset + 2).toString());
    }
  }

  @Test
  void onlySuggestsStartsInTheNextThirtyDays() throws Exception {
    CustomerSession guest = new CustomerSession();
    long unitId = 3;
    LocalDate arrival = futureDate(600);
    var stay = stayQuery(unitId, arrival, arrival.plusDays(2));
    long block = jdbc.queryForObject(
        "insert into availability_blocks (unit_id, start_date, end_date) values (?, ?, ?) returning id",
        Long.class, unitId, arrival, arrival.plusDays(30));

    var suggestions = guest.get("availability", stay).data(200).path("suggestions");
    assertThat(suggestions.size()).isEqualTo(1);
    assertThat(suggestions.path(0).path("check_in").asString()).isEqualTo(arrival.plusDays(30).toString());
    assertThat(suggestions.path(0).path("check_out").asString()).isEqualTo(arrival.plusDays(32).toString());

    jdbc.update("update availability_blocks set end_date = ? where id = ?", arrival.plusDays(31), block);
    assertThat(guest.get("availability", stay).data(200).path("suggestions").size()).isZero();
  }

  @Test
  void savingAndRemovingFavoritesIsPrivateAndIdempotent() throws Exception {
    CustomerSession guest = new CustomerSession();
    assertThat(guest.get("favorites", Map.of()).statusCode()).isEqualTo(401);
    CustomerSession owner = customer("favorite-owner");
    CustomerSession stranger = customer("favorite-stranger");
    Map<String, Object> unit = Map.of("unit_id", 1);
    assertThat(owner.post("add-favorite", unit, false).statusCode()).isEqualTo(419);

    HttpRequest first = owner.postRequest("add-favorite", unit, true);
    HttpRequest second = owner.postRequest("add-favorite", unit, true);
    var a = owner.client.sendAsync(first, HttpResponse.BodyHandlers.ofString());
    var b = owner.client.sendAsync(second, HttpResponse.BodyHandlers.ofString());
    assertThat(List.of(a.get(20, TimeUnit.SECONDS).statusCode(), b.get(20, TimeUnit.SECONDS).statusCode()))
        .containsExactlyInAnyOrder(201, 200);
    var favorites = owner.get("favorites", Map.of()).data(200);
    assertThat(favorites.size()).isEqualTo(1);
    assertThat(favorites.path(0).path("unit").path("id").asInt()).isEqualTo(1);
    assertThat(favorites.path(0).path("unit").path("cover_image").path("image_url").asString()).isNotBlank();

    assertThat(stranger.get("favorites", Map.of()).data(200).size()).isZero();
    stranger.post("remove-favorite", unit).data(200);
    assertThat(owner.get("favorites", Map.of()).data(200).size()).isEqualTo(1);
    owner.post("remove-favorite", unit).data(200);
    owner.post("remove-favorite", unit).data(200);
    assertThat(owner.get("favorites", Map.of()).data(200).size()).isZero();
    assertThat(owner.post("add-favorite", Map.of("unit_id", 999999)).statusCode()).isEqualTo(404);
  }

  @Test
  void receptionRequiresItsOwnLoginAndProtectsWritesWithItsOwnCsrfToken() throws Exception {
    CustomerSession customer = customer("reception-isolation");
    ReceptionSession reception = new ReceptionSession(customer);
    StaffAccount staff = staffAccount("isolation-staff", "staff");
    String week = "/calendar?from=" + futureDate(75);

    var guest = reception.get("/session");
    assertThat(guest.statusCode()).isEqualTo(200);
    assertThat(guest.body().path("enabled").asBoolean()).isTrue();
    assertThat(guest.body().path("authenticated").asBoolean()).isFalse();
    assertThat(reception.get(week).statusCode()).isEqualTo(401);

    Map<String, Object> credentials = staff.credentials();
    assertThat(reception.post("/login", credentials, null).statusCode()).isEqualTo(419);
    String customerToken = customer.get("get-csrf", Map.of()).data(200).path("token").asString();
    assertThat(reception.post("/login", credentials, customerToken).statusCode()).isEqualTo(419);

    reception.login(staff);
    assertThat(reception.get(week).statusCode()).isEqualTo(200);
    assertThat(customer.get("check-auth", Map.of()).body().path("authenticated").asBoolean()).isTrue();
    assertThat(reception.get("/session").body().path("can_manage_blocks").asBoolean()).isFalse();

    var logout = reception.post("/logout", Map.of());
    assertThat(logout.statusCode()).isIn(200, 204);
    assertThat(reception.get(week).statusCode()).isEqualTo(401);
    assertThat(customer.get("check-auth", Map.of()).body().path("authenticated").asBoolean()).isTrue();

    reception.login(staff);
    jdbc.update("update public.admins set status = 'inactive' where id = ?", staff.id());
    assertThat(reception.get(week).statusCode()).isIn(401, 403);
    assertThat(reception.get("/session").body().path("authenticated").asBoolean()).isFalse();
  }

  @Test
  void staffCanConfirmOnceAndTheAuditRecordsWhoChangedTheBooking() throws Exception {
    CustomerSession owner = customer("reception-confirm");
    LocalDate arrival = futureDate(80);
    LocalDate departure = arrival.plusDays(2);
    var created = owner.post("create-booking", booking(6, arrival, departure)).data(201);
    long id = created.path("id").asLong();
    StaffAccount staff = staffAccount("confirm-staff", "staff");
    ReceptionSession reception = new ReceptionSession(new CustomerSession());
    reception.login(staff);

    String statusRoute = "/bookings/" + id + "/status";
    assertThat(reception.post(statusRoute, Map.of("expected_status", "pending", "status", "checkout"))
        .statusCode()).isEqualTo(422);
    var confirmation = Map.<String, Object>of("expected_status", "pending", "status", "confirmed");
    var a = reception.sendAsync(reception.request(statusRoute, confirmation, reception.token()));
    var b = reception.sendAsync(reception.request(statusRoute, confirmation, reception.token()));
    assertThat(List.of(a.get(20, TimeUnit.SECONDS).statusCode(), b.get(20, TimeUnit.SECONDS).statusCode()))
        .containsExactlyInAnyOrder(200, 409);

    var week = reception.get("/calendar?from=" + arrival);
    assertThat(week.statusCode()).isEqualTo(200);
    assertThat(week.body().path("from").asString()).isEqualTo(arrival.toString());
    assertThat(week.body().path("to").asString()).isEqualTo(arrival.plusDays(7).toString());
    assertThat(week.body().path("bookings").findValues("status").stream()
        .map(JsonNode::asString).toList()).contains("confirmed");
    var detail = owner.get("booking-detail", Map.of("id", Long.toString(id))).data(200);
    assertThat(detail.path("status").asString()).isEqualTo("confirmed");
    assertThat(detail.path("confirmed_at").asString()).isNotBlank();
    assertThat(reception.post(statusRoute, Map.of("expected_status", "confirmed", "status", "checkin"))
        .statusCode()).isEqualTo(422);
    assertThat(jdbc.queryForList("""
        select concat(old_value_json->>'status', '->', new_value_json->>'status')
        from public.audit_logs
        where admin_id = ? and entity_id = ? and action = 'CHANGE_BOOKING_STATUS'
        """, String.class, staff.id(), id)).containsExactly("pending->confirmed");
  }

  @Test
  void receptionRecordsArrivalAndDepartureOnTheScheduledDates() throws Exception {
    StaffAccount staff = staffAccount("lifecycle-staff", "staff");
    ReceptionSession reception = new ReceptionSession(new CustomerSession());
    reception.login(staff);
    long booking = jdbc.queryForObject("""
        insert into public.bookings
          (booking_reference, customer_id, unit_id, guest_name, check_in, check_out,
           number_of_nights, subtotal, total_amount, status)
        values ('RECEPTION-LIFECYCLE', 1, 16, 'Demo', current_date - 1, current_date,
                1, 100000, 100000, 'confirmed')
        returning id
        """, Long.class);
    String route = "/bookings/" + booking + "/status";
    var checkin = Map.<String, Object>of("expected_status", "confirmed", "status", "checkin");
    assertThat(reception.post(route, checkin).statusCode()).isEqualTo(422);

    jdbc.update("update public.bookings set check_in = current_date, check_out = current_date + 1 where id = ?",
        booking);
    assertThat(reception.post(route, checkin).statusCode()).isEqualTo(200);
    var checkout = Map.<String, Object>of("expected_status", "checkin", "status", "checkout");
    assertThat(reception.post(route, checkout).statusCode()).isEqualTo(422);

    jdbc.update("update public.bookings set check_in = current_date - 1, check_out = current_date where id = ?",
        booking);
    var result = reception.post(route, checkout);
    assertThat(result.statusCode()).isEqualTo(200);
    assertThat(result.body().path("status").asString()).isEqualTo("checkout");
    assertThat(jdbc.queryForObject("""
        select checked_in_at is not null and checked_out_at is not null
        from public.bookings where id = ?
        """, Boolean.class, booking)).isTrue();
  }

  @Test
  void managerBlockAffectsAvailabilityAndCanBeRemoved() throws Exception {
    CustomerSession guest = new CustomerSession();
    LocalDate start = futureDate(90);
    LocalDate end = start.plusDays(2);
    var block = block(6, start, end);
    var stay = stayQuery(6, start, end);
    StaffAccount staff = staffAccount("block-staff", "staff");
    ReceptionSession staffSession = new ReceptionSession(new CustomerSession());
    staffSession.login(staff);
    assertThat(staffSession.post("/blocks", block).statusCode()).isEqualTo(403);
    assertThat(guest.get("availability", stay).data(200).path("available").asBoolean()).isTrue();

    StaffAccount manager = staffAccount("block-manager", "manager");
    ReceptionSession reception = new ReceptionSession(new CustomerSession());
    reception.login(manager);
    assertThat(reception.get("/session").body().path("can_manage_blocks").asBoolean()).isTrue();
    assertThat(reception.post("/blocks", block).statusCode()).isEqualTo(201);
    assertThat(reception.post("/blocks", block).statusCode()).isEqualTo(409);
    assertThat(guest.get("availability", stay).data(200).path("available").asBoolean()).isFalse();
    assertThat(guest.get("availability", stayQuery(6, end, end.plusDays(1))).data(200)
        .path("available").asBoolean()).isTrue();

    var calendar = reception.get("/calendar?from=" + start);
    assertThat(calendar.statusCode()).isEqualTo(200);
    var blocks = calendar.body().path("blocks");
    assertThat(blocks.size()).isEqualTo(1);
    long id = blocks.path(0).path("id").asLong();
    assertThat(id).isPositive();
    assertThat(blocks.path(0).path("reason").asString()).isEqualTo("maintenance");
    String removal = "/blocks/" + id + "/remove";
    assertThat(staffSession.post(removal, Map.of()).statusCode()).isEqualTo(403);
    assertThat(reception.post(removal, Map.of()).statusCode()).isIn(200, 204);
    assertThat(guest.get("availability", stay).data(200).path("available").asBoolean()).isTrue();
    assertThat(jdbc.queryForList("""
        select action from public.audit_logs
        where admin_id = ? and entity_id = ? and entity_type = 'availability_blocks'
        order by id
        """, String.class, manager.id(), id)).containsExactly("BLOCK_DATES", "UNBLOCK_DATES");
  }

  @Test
  void competingCustomerBookingAndManagerBlockCannotBothWin() throws Exception {
    CustomerSession owner = customer("reception-block-race");
    StaffAccount manager = staffAccount("race-manager", "manager");
    ReceptionSession reception = new ReceptionSession(new CustomerSession());
    reception.login(manager);
    long unit = 7;
    LocalDate start = futureDate(110);
    LocalDate end = start.plusDays(2);

    HttpRequest booking = owner.postRequest("create-booking", booking(unit, start, end), true);
    HttpRequest block = reception.request("/blocks", block(unit, start, end), reception.token());
    var a = owner.client.sendAsync(booking, HttpResponse.BodyHandlers.ofString());
    var b = reception.sendAsync(block);
    assertThat(List.of(a.get(20, TimeUnit.SECONDS).statusCode(), b.get(20, TimeUnit.SECONDS).statusCode()))
        .containsExactlyInAnyOrder(201, 409);
  }

  private StaffAccount staffAccount(String username, String role) {
    String hash = new BCryptPasswordEncoder(4).encode(TEST_PASSWORD);
    long id = jdbc.queryForObject("""
        insert into public.admins (username, email, password_hash, full_name, role)
        values (?, ?, ?, ?, ?) returning id
        """, Long.class, username, username + "@example.test", hash, "Reception test " + username, role);
    return new StaffAccount(id, username);
  }

  private static Map<String, Object> block(long unitId, LocalDate start, LocalDate end) {
    return Map.of("unit_id", unitId, "start_date", start.toString(), "end_date", end.toString(),
        "reason", "maintenance");
  }

  private record StaffAccount(long id, String username) {
    Map<String, Object> credentials() {
      return Map.of("username", username, "password", TEST_PASSWORD);
    }
  }

  private final class ReceptionSession {
    private final CustomerSession browser;

    ReceptionSession(CustomerSession browser) {
      this.browser = browser;
    }

    void login(StaffAccount staff) throws Exception {
      var result = post("/login", staff.credentials());
      assertThat(result.statusCode()).as("Reception login: %s", result.body()).isEqualTo(200);
      assertThat(get("/session").body().path("authenticated").asBoolean()).isTrue();
    }

    String token() throws Exception {
      String token = get("/session").body().path("csrf").asString();
      assertThat(token).isNotBlank();
      return token;
    }

    Result get(String path) throws Exception {
      return browser.send(builder(path).GET().build());
    }

    Result post(String path, Map<String, Object> body) throws Exception {
      return post(path, body, token());
    }

    Result post(String path, Map<String, Object> body, String csrf) throws Exception {
      return browser.send(request(path, body, csrf));
    }

    HttpRequest request(String path, Map<String, Object> body, String csrf) {
      var builder = builder(path).header("Content-Type", "application/json")
          .POST(HttpRequest.BodyPublishers.ofString(JSON.writeValueAsString(body)));
      if (csrf != null) builder.header("X-CSRF-Token", csrf);
      return builder.build();
    }

    java.util.concurrent.CompletableFuture<HttpResponse<String>> sendAsync(HttpRequest request) {
      return browser.client.sendAsync(request, HttpResponse.BodyHandlers.ofString());
    }

    private HttpRequest.Builder builder(String path) {
      return HttpRequest.newBuilder(URI.create("http://127.0.0.1:" + port + "/api/reception" + path))
          .timeout(HTTP_TIMEOUT);
    }
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
