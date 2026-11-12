package vn.yang.homestay;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import java.util.LinkedHashMap;
import java.util.Map;
import org.springframework.dao.DataAccessException;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.http.converter.HttpMessageNotReadableException;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.InternalAuthenticationServiceException;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.AuthenticationException;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.authentication.logout.SecurityContextLogoutHandler;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.web.csrf.CsrfToken;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

@RestController
@RequestMapping(value = "/backend/customer-api.php", produces = MediaType.APPLICATION_JSON_VALUE)
class CustomerApi {
  private final CatalogService catalog;
  private final BookingService bookings;
  private final CustomerAccounts accounts;
  private final AuthenticationManager authenticationManager;
  private final HttpSessionSecurityContextRepository contexts;

  CustomerApi(CatalogService catalog, BookingService bookings, CustomerAccounts accounts,
      AuthenticationManager authenticationManager, HttpSessionSecurityContextRepository contexts) {
    this.catalog = catalog;
    this.bookings = bookings;
    this.accounts = accounts;
    this.authenticationManager = authenticationManager;
    this.contexts = contexts;
  }

  @GetMapping
  Object read(@RequestParam Map<String, String> query, Authentication authentication, CsrfToken csrf) {
    return switch (query.getOrDefault("action", "")) {
      case "get-csrf" -> success(Map.of("token", csrf.getToken()));
      case "check-auth" -> checkAuth(authentication);
      case "get-profile" -> success(requireCustomer(authentication));
      case "units" -> success(catalog.units(query));
      case "unit-detail" -> success(catalog.unit(id(query, "id")));
      case "properties" -> success(catalog.properties(query));
      case "property-detail" -> success(catalog.property(id(query, "id")));
      case "units-by-property" -> success(catalog.unitsByProperty(id(query, "property_id")));
      case "availability" -> success(bookings.availability(query));
      case "booking-quote" -> success(bookings.quote(query));
      case "my-bookings" -> success(bookings.list(customerId(authentication), query.get("status")));
      case "booking-detail" -> success(bookings.detail(customerId(authentication), id(query, "id")));
      case "login", "register", "logout", "create-booking", "cancel-booking" ->
          throw new ApiProblem(405, "Thao tác này cần dùng POST.");
      default -> throw unsupported();
    };
  }

  @PostMapping(consumes = MediaType.APPLICATION_JSON_VALUE)
  Object write(@RequestParam(defaultValue = "") String action, @RequestBody Map<String, Object> input,
      Authentication authentication, HttpServletRequest request, HttpServletResponse response) {
    return switch (action) {
      case "register" -> {
        accounts.register(input);
        yield ResponseEntity.status(201).body(new Result(true, "Đã tạo tài khoản.", null));
      }
      case "login" -> success(login(input, request, response));
      case "logout" -> {
        new SecurityContextLogoutHandler().logout(request, response, authentication);
        yield success(null);
      }
      case "create-booking" -> ResponseEntity.status(201).body(new Result(
          true, "Đã tạo yêu cầu đặt chỗ.", bookings.create(customerId(authentication), input)));
      case "cancel-booking" -> success(bookings.cancel(customerId(authentication), input));
      default -> throw unsupported();
    };
  }

  private Map<String, Object> login(Map<String, Object> input, HttpServletRequest request,
      HttpServletResponse response) {
    var credentials = UsernamePasswordAuthenticationToken.unauthenticated(
        CustomerAccounts.email(input), CustomerAccounts.password(input));
    Authentication authenticated;
    try {
      authenticated = authenticationManager.authenticate(credentials);
    } catch (InternalAuthenticationServiceException error) {
      throw new ApiProblem(503, "Dịch vụ đăng nhập tạm thời chưa sẵn sàng.");
    } catch (AuthenticationException error) {
      throw new ApiProblem(401, "Email hoặc mật khẩu không hợp lệ.");
    }
    var customer = requireCustomer(authenticated);
    request.getSession();
    request.changeSessionId();
    var context = SecurityContextHolder.createEmptyContext();
    context.setAuthentication(authenticated);
    SecurityContextHolder.setContext(context);
    contexts.saveContext(context, request, response);
    return customer;
  }

  private Map<String, Object> checkAuth(Authentication authentication) {
    var customer = currentCustomer(authentication);
    Map<String, Object> payload = new LinkedHashMap<>();
    payload.put("success", true);
    payload.put("authenticated", customer != null);
    payload.put("data", customer);
    return payload;
  }

  private Map<String, Object> currentCustomer(Authentication authentication) {
    return authentication == null ? null : accounts.activeCustomer(authentication.getName());
  }

  private Map<String, Object> requireCustomer(Authentication authentication) {
    var customer = currentCustomer(authentication);
    if (customer == null) throw new ApiProblem(401, "Vui lòng đăng nhập.");
    return customer;
  }

  private long customerId(Authentication authentication) {
    return ((Number) requireCustomer(authentication).get("id")).longValue();
  }

  private static long id(Map<String, String> query, String field) {
    try {
      long value = Long.parseLong(query.getOrDefault(field, ""));
      if (value > 0) return value;
    } catch (NumberFormatException error) {
      // Both missing and invalid identifiers return the same response.
    }
    throw new ApiProblem(422, "Mã bản ghi không hợp lệ.");
  }

  private static ApiProblem unsupported() {
    return new ApiProblem(501, "Tính năng này hiện có trong bản PHP của YangHomestay.");
  }

  @ExceptionHandler(ApiProblem.class)
  ResponseEntity<Result> apiError(ApiProblem error) {
    return ResponseEntity.status(error.status()).body(new Result(false, error.getMessage(), null));
  }

  @ExceptionHandler(HttpMessageNotReadableException.class)
  ResponseEntity<Result> invalidJson() {
    return ResponseEntity.badRequest().body(new Result(false, "Nội dung JSON không hợp lệ.", null));
  }

  @ExceptionHandler(DataAccessException.class)
  ResponseEntity<Result> databaseUnavailable() {
    return ResponseEntity.status(503).body(new Result(false, "Dữ liệu tạm thời chưa sẵn sàng.", null));
  }

  private static Result success(Object data) {
    return new Result(true, "OK", data);
  }

  record Result(boolean success, String message, Object data) {}
}
