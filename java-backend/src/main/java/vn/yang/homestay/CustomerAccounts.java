package vn.yang.homestay;

import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import org.springframework.dao.DuplicateKeyException;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;

@Service
class CustomerAccounts implements UserDetailsService {
  private final JdbcTemplate jdbc;
  private final PasswordEncoder passwords;

  CustomerAccounts(JdbcTemplate jdbc, PasswordEncoder passwords) {
    this.jdbc = jdbc;
    this.passwords = passwords;
  }

  @Override
  public UserDetails loadUserByUsername(String email) {
    var accounts = jdbc.query(
        "select email, password_hash, status from customers where email = ?",
        (rs, row) -> User.withUsername(rs.getString("email"))
            .password(rs.getString("password_hash"))
            .roles("CUSTOMER")
            .disabled(!"active".equals(rs.getString("status")))
            .build(),
        email);
    if (accounts.isEmpty()) throw new UsernameNotFoundException("Customer not found");
    return accounts.get(0);
  }

  Map<String, Object> activeCustomer(String email) {
    List<Map<String, Object>> accounts = jdbc.queryForList(
        "select id, full_name, email, phone, avatar_url, status from customers where email = ? and status = 'active'",
        email);
    return accounts.isEmpty() ? null : accounts.get(0);
  }

  void register(Map<String, Object> input) {
    String name = text(input, "full_name").strip();
    String email = email(input);
    String phone = text(input, "phone").strip();
    String password = password(input);
    if (name.isBlank() || name.length() > 100) throw new ApiProblem(422, "Họ tên phải từ 1 đến 100 ký tự.");
    if (!phone.matches("(?:0|\\+84)[0-9]{9}")) throw new ApiProblem(422, "Số điện thoại không hợp lệ.");
    if (password.length() < 8 || !password.equals(text(input, "confirm_password"))) {
      throw new ApiProblem(422, "Mật khẩu phải có ít nhất 8 ký tự và khớp xác nhận.");
    }
    try {
      jdbc.update(
          "insert into customers (full_name, email, phone, password_hash) values (?, ?, ?, ?)",
          name, email, phone, passwords.encode(password));
    } catch (DuplicateKeyException exception) {
      throw new ApiProblem(409, "Email đã được sử dụng.");
    }
  }

  static String email(Map<String, Object> input) {
    String email = text(input, "email").strip().toLowerCase(Locale.ROOT);
    if (email.length() > 100 || !email.matches("[^\\s@]+@[^\\s@]+\\.[^\\s@]+")) {
      throw new ApiProblem(422, "Email không hợp lệ.");
    }
    return email;
  }

  static String password(Map<String, Object> input) {
    String password = text(input, "password");
    if (password.isEmpty() || password.getBytes(StandardCharsets.UTF_8).length > 72) {
      throw new ApiProblem(422, "Mật khẩu không hợp lệ.");
    }
    return password;
  }

  private static String text(Map<String, Object> input, String name) {
    return input.get(name) instanceof String value ? value : "";
  }
}
