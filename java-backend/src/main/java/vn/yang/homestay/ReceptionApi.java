package vn.yang.homestay;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import java.nio.charset.StandardCharsets;
import java.util.Map;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.dao.DataAccessException;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.http.converter.HttpMessageNotReadableException;
import org.springframework.security.authentication.InternalAuthenticationServiceException;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.AuthenticationException;
import org.springframework.security.web.csrf.CsrfToken;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

@RestController
@RequestMapping(path = "/api/reception", produces = MediaType.APPLICATION_JSON_VALUE)
class ReceptionApi {
  private final ReceptionService calendar;
  private final ReceptionAccounts accounts;
  private final ReceptionSecurity security;
  private final boolean enabled;

  ReceptionApi(ReceptionService calendar, ReceptionAccounts accounts, ReceptionSecurity security,
      @Value("${yanghomestay.reception.enabled}") boolean enabled) {
    this.calendar = calendar;
    this.accounts = accounts;
    this.security = security;
    this.enabled = enabled;
  }

  @GetMapping("/session")
  Map<String, Object> session(Authentication authentication, CsrfToken csrf) {
    var staff = enabled ? accounts.current(authentication) : null;
    return Map.of(
        "enabled", enabled,
        "authenticated", staff != null,
        "name", staff == null ? "" : staff.name(),
        "can_manage_blocks", staff != null && staff.canManageBlocks(),
        "csrf", enabled ? csrf.getToken() : "");
  }

  @PostMapping(path = "/login", consumes = MediaType.APPLICATION_JSON_VALUE)
  Map<String, Boolean> login(@RequestBody Map<String, Object> input, HttpServletRequest request,
      HttpServletResponse response) {
    requireEnabled();
    String username = text(input, "username").strip();
    String password = text(input, "password");
    if (username.isBlank() || username.length() > 50 || password.isEmpty()
        || password.getBytes(StandardCharsets.UTF_8).length > 72) {
      throw invalidCredentials();
    }
    try {
      security.signIn(accounts.authenticate(username, password), request, response);
      return Map.of("authenticated", true);
    } catch (InternalAuthenticationServiceException error) {
      throw new ApiProblem(503, "Dịch vụ đăng nhập tạm thời chưa sẵn sàng.");
    } catch (AuthenticationException error) {
      throw invalidCredentials();
    }
  }

  @PostMapping(path = "/logout", consumes = MediaType.APPLICATION_JSON_VALUE)
  ResponseEntity<Void> logout(HttpServletRequest request, HttpServletResponse response) {
    security.signOut(request, response);
    return ResponseEntity.noContent().build();
  }

  @GetMapping("/calendar")
  Object calendar(@RequestParam(required = false) String from, Authentication authentication) {
    requireStaff(authentication);
    return calendar.calendar(from);
  }

  @PostMapping(path = "/bookings/{id}/status", consumes = MediaType.APPLICATION_JSON_VALUE)
  Object transition(@PathVariable long id, @RequestBody Map<String, Object> input,
      Authentication authentication) {
    var staff = requireStaff(authentication);
    return calendar.transition(staff.id(), id, text(input, "expected_status"), text(input, "status"));
  }

  @PostMapping(path = "/blocks", consumes = MediaType.APPLICATION_JSON_VALUE)
  ResponseEntity<Object> addBlock(@RequestBody Map<String, Object> input,
      Authentication authentication) {
    var staff = requireManager(authentication);
    long unitId;
    try {
      unitId = Long.parseLong(String.valueOf(input.get("unit_id")));
    } catch (NumberFormatException error) {
      throw new ApiProblem(422, "Mã căn không hợp lệ.");
    }
    return ResponseEntity.status(201).body(calendar.addBlock(
        staff.id(), unitId, text(input, "start_date"), text(input, "end_date"), text(input, "reason")));
  }

  @PostMapping(path = "/blocks/{id}/remove", consumes = MediaType.APPLICATION_JSON_VALUE)
  ResponseEntity<Void> removeBlock(@PathVariable long id, Authentication authentication) {
    var staff = requireManager(authentication);
    calendar.removeBlock(staff.id(), id);
    return ResponseEntity.noContent().build();
  }

  private ReceptionAccounts.Staff requireManager(Authentication authentication) {
    var staff = requireStaff(authentication);
    if (!staff.canManageBlocks()) throw new ApiProblem(403, "Cần quyền quản lý để đóng hoặc mở ngày.");
    return staff;
  }

  private ReceptionAccounts.Staff requireStaff(Authentication authentication) {
    requireEnabled();
    var staff = accounts.current(authentication);
    if (staff == null) throw new ApiProblem(401, "Vui lòng đăng nhập tài khoản nội bộ.");
    return staff;
  }

  private void requireEnabled() {
    if (!enabled) throw new ApiProblem(404, "Khu lễ tân chưa được bật.");
  }

  private static String text(Map<String, Object> input, String field) {
    return input.get(field) instanceof String value ? value : "";
  }

  private static ApiProblem invalidCredentials() {
    return new ApiProblem(401, "Tên đăng nhập hoặc mật khẩu không hợp lệ.");
  }

  @ExceptionHandler(ApiProblem.class)
  ResponseEntity<Map<String, String>> apiError(ApiProblem error) {
    return ResponseEntity.status(error.status()).body(Map.of("message", error.getMessage()));
  }

  @ExceptionHandler(HttpMessageNotReadableException.class)
  ResponseEntity<Map<String, String>> invalidJson() {
    return ResponseEntity.badRequest().body(Map.of("message", "Nội dung JSON không hợp lệ."));
  }

  @ExceptionHandler(DataAccessException.class)
  ResponseEntity<Map<String, String>> databaseUnavailable() {
    return ResponseEntity.status(503).body(Map.of("message", "Dữ liệu tạm thời chưa sẵn sàng."));
  }
}
