package vn.yang.homestay;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.MediaType;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.ProviderManager;
import org.springframework.security.authentication.dao.DaoAuthenticationProvider;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configurers.AbstractHttpConfigurer;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.web.csrf.CsrfTokenRequestAttributeHandler;
import org.springframework.security.web.csrf.InvalidCsrfTokenException;
import org.springframework.security.web.csrf.MissingCsrfTokenException;
import org.springframework.security.web.csrf.HttpSessionCsrfTokenRepository;

@Configuration
class CustomerSecurity {
  @Bean
  PasswordEncoder passwordEncoder() {
    return new BCryptPasswordEncoder(12);
  }

  @Bean
  AuthenticationManager authenticationManager(UserDetailsService customers, PasswordEncoder encoder) {
    var provider = new DaoAuthenticationProvider(customers);
    provider.setPasswordEncoder(encoder);
    return new ProviderManager(provider);
  }

  @Bean
  HttpSessionSecurityContextRepository securityContexts() {
    return new HttpSessionSecurityContextRepository();
  }

  @Bean
  SecurityFilterChain customerRoutes(HttpSecurity http, HttpSessionSecurityContextRepository contexts)
      throws Exception {
    return http
        .authorizeHttpRequests(routes -> routes
            .requestMatchers("/", "/health", "/error", "/customer", "/customer/**",
                "/assets/favicon.svg", "/assets/images/**", "/backend/customer-api.php").permitAll()
            .anyRequest().denyAll())
        .securityContext(security -> security.securityContextRepository(contexts))
        .csrf(csrf -> csrf
            .csrfTokenRepository(new HttpSessionCsrfTokenRepository())
            .csrfTokenRequestHandler(new CsrfTokenRequestAttributeHandler()))
        .exceptionHandling(errors -> errors.accessDeniedHandler((request, response, error) -> {
          if (error instanceof MissingCsrfTokenException || error instanceof InvalidCsrfTokenException) {
            response.setStatus(419);
            response.setContentType(MediaType.APPLICATION_JSON_VALUE);
            response.setCharacterEncoding("UTF-8");
            response.getWriter().write(
                "{\"success\":false,\"message\":\"Phiên đã hết hạn, vui lòng thử lại.\"}");
          } else {
            response.sendError(403);
          }
        }))
        .logout(AbstractHttpConfigurer::disable)
        .requestCache(AbstractHttpConfigurer::disable)
        .build();
  }
}
