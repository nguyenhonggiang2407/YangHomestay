<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * SECURITY — security headers, logging (không bao giờ ghi secret), input limits
 * ═════════════════════════════════════════════════════════════════════════
 */
final class Security
{
    /**
     * Gửi security headers cơ bản.
     * KHÔNG bật CSP mặc định để tránh làm hỏng frontend hiện tại
     * (xem ghi chú Phase 3 §70 — CSP chỉ bật sau khi test kỹ).
     */
    public static function sendHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('X-Permitted-Cross-Domain-Policies: none');
    }

    /**
     * IP khách hàng (an toàn khi chạy CLI — không có $_SERVER).
     */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '' && PHP_SAPI === 'cli') {
            return 'cli';
        }
        return substr((string)$ip, 0, 45);
    }

    public static function userAgent(): string
    {
        return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    /**
     * Ghi log lỗi nội bộ. Tuyệt đối KHÔNG ghi:
     *   - supabase secret / service role key / sb_secret_...
     *   - password / password_hash
     *   - Authorization header
     * Các key chứa secret/password/token/authorization bị loại khỏi $data.
     */
    public static function logError(string $context, string $event, array $data = []): void
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/key|secret|password|passwd|authorization|bearer|token/i', (string)$key)) {
                continue;
            }
            $safe[$key] = is_scalar($value) || $value === null ? $value : '[' . gettype($value) . ']';
        }

        $line = date('c')
            . ' [' . $context . '] ' . $event
            . ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logDir . '/error.log', $line . PHP_EOL, FILE_APPEND);
    }

    /**
     * Giới hạn độ dài input (chống gửi text khổng lồ).
     */
    public static function limitInput($value, int $maxLen): string
    {
        $value = (string)($value ?? '');
        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }
        return trim($value);
    }

    /**
     * Chặn request body quá lớn (mặc định 1MB). Gọi ở đầu controller POST/PATCH.
     */
    public static function assertBodySize(int $maxBytes = 1048576): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($len > $maxBytes) {
            ApiResponse::fail('Dữ liệu gửi lên quá lớn', 413);
        }
    }

    /**
     * Chuẩn hoá số tiền VND (integer). Trả về int hoặc null nếu không hợp lệ.
     */
    public static function toVnd($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            return (int)$value;
        }
        return null;
    }
}
