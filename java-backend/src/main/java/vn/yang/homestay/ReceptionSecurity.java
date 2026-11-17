package vn.yang.homestay;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.core.annotation.Order;
import org.springframework.http.HttpMethod;
import org.springframework.http.MediaType;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configurers.AbstractHttpConfigurer;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.web.csrf.CsrfTokenRequestAttributeHandler;
import org.springframework.security.web.csrf.HttpSessionCsrfTokenRepository;
import org.springframework.security.web.csrf.InvalidCsrfTokenException;
import org.springframework.security.web.csrf.MissingCsrfTokenException;

@Configuration
class ReceptionSecurity {
  private final HttpSessionSecurityContextRepository contexts =
      new HttpSessionSecurityContextRepository();
  private final HttpSessionCsrfTokenRepository tokens = new HttpSessionCsrfTokenRepository();

  ReceptionSecurity() {
    contexts.setSpringSecurityContextKey("YANGHOMESTAY_RECEPTION_CONTEXT");
    tokens.setSessionAttributeName("YANGHOMESTAY_RECEPTION_CSRF");
  }

  @Bean
  @Order(1)
  SecurityFilterChain receptionRoutes(HttpSecurity http) throws Exception {
    return http
        .securityMatcher("/api/reception/**")
        .authorizeHttpRequests(routes -> routes
            .requestMatchers(HttpMethod.GET, "/api/reception/session").permitAll()
            .requestMatchers(HttpMethod.POST, "/api/reception/login").permitAll()
            .anyRequest().hasRole("RECEPTION"))
        .securityContext(security -> security.securityContextRepository(contexts))
        .csrf(csrf -> csrf
            .csrfTokenRepository(tokens)
            .csrfTokenRequestHandler(new CsrfTokenRequestAttributeHandler()))
        .exceptionHandling(errors -> errors
            .authenticationEntryPoint((request, response, error) ->
                reject(response, 401, "Vui lòng đăng nhập tài khoản nội bộ."))
            .accessDeniedHandler((request, response, error) -> {
              if (error instanceof InvalidCsrfTokenException || error instanceof MissingCsrfTokenException) {
                reject(response, 419, "Phiên đã hết hạn, vui lòng thử lại.");
              } else {
                reject(response, 403, "Tài khoản không có quyền thực hiện thao tác này.");
              }
            }))
        .logout(AbstractHttpConfigurer::disable)
        .requestCache(AbstractHttpConfigurer::disable)
        .build();
  }

  private static void reject(HttpServletResponse response, int status, String message)
      throws java.io.IOException {
    response.setStatus(status);
    response.setContentType(MediaType.APPLICATION_JSON_VALUE);
    response.setCharacterEncoding("UTF-8");
    response.getWriter().write("{\"message\":\"" + message + "\"}");
  }

  void signIn(Authentication authentication, HttpServletRequest request, HttpServletResponse response) {
    request.getSession();
    request.changeSessionId();
    var context = SecurityContextHolder.createEmptyContext();
    context.setAuthentication(authentication);
    SecurityContextHolder.setContext(context);
    contexts.saveContext(context, request, response);
    tokens.saveToken(null, request, response);
  }

  void signOut(HttpServletRequest request, HttpServletResponse response) {
    SecurityContextHolder.clearContext();
    contexts.saveContext(SecurityContextHolder.createEmptyContext(), request, response);
    tokens.saveToken(null, request, response);
  }
}
