package vn.yang.homestay;

final class ApiProblem extends RuntimeException {
  private final int status;

  ApiProblem(int status, String message) {
    super(message);
    this.status = status;
  }

  int status() {
    return status;
  }
}
