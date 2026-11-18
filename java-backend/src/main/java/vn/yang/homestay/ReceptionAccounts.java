package vn.yang.homestay;

import java.util.List;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.ProviderManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.authentication.dao.DaoAuthenticationProvider;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;

@Service
class ReceptionAccounts {
  private static final String ENABLED_ACCOUNTS = """
      from public.admins where username = ? and status = 'active'
      and role in ('staff', 'manager', 'super_admin')
      """;
  private final JdbcTemplate jdbc;
  private final AuthenticationManager login;

  ReceptionAccounts(JdbcTemplate jdbc, PasswordEncoder passwords) {
    this.jdbc = jdbc;
    var provider = new DaoAuthenticationProvider(username -> {
      List<String> hashes = jdbc.queryForList("select password_hash " + ENABLED_ACCOUNTS,
          String.class, username);
      if (hashes.isEmpty()) throw new UsernameNotFoundException("Reception user not found");
      return User.withUsername(username).password(hashes.get(0)).roles("RECEPTION").build();
    });
    provider.setPasswordEncoder(passwords);
    login = new ProviderManager(provider);
  }

  Authentication authenticate(String username, String password) {
    return login.authenticate(UsernamePasswordAuthenticationToken.unauthenticated(username, password));
  }

  Staff current(Authentication authentication) {
    if (authentication == null || authentication.getAuthorities().stream()
        .noneMatch(authority -> authority.getAuthority().equals("ROLE_RECEPTION"))) {
      return null;
    }
    var accounts = jdbc.query("select id, full_name, role " + ENABLED_ACCOUNTS,
        (row, index) -> new Staff(row.getLong("id"), row.getString("full_name"), row.getString("role")),
        authentication.getName());
    return accounts.isEmpty() ? null : accounts.get(0);
  }

  record Staff(long id, String name, String role) {
    boolean canManageBlocks() {
      return role.equals("manager") || role.equals("super_admin");
    }
  }
}
