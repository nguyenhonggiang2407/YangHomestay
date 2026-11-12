package vn.yang.homestay;

import java.util.Map;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.ResponseBody;

@SpringBootApplication
public class YangHomestayApplication {
  public static void main(String[] args) {
    SpringApplication.run(YangHomestayApplication.class, args);
  }
}

@Controller
class CustomerPages {
  private final JdbcTemplate jdbc;

  CustomerPages(JdbcTemplate jdbc) {
    this.jdbc = jdbc;
  }

  @GetMapping({"/", "/customer", "/customer/"})
  String home() {
    return "redirect:/customer/index.html";
  }

  @GetMapping("/health")
  @ResponseBody
  Map<String, String> health() {
    jdbc.queryForObject("select 1", Integer.class);
    return Map.of("status", "ok");
  }
}
