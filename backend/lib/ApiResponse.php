<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * API RESPONSE — format JSON thống nhất toàn backend YangHomestay
 *
 * Success:
 *   { "success": true,  "message": "OK", "data": {} }
 * Error:
 *   { "success": false, "message": "..." }            (+ "errors": {} khi validation)
 *
 * KHÔNG trả raw Supabase/PostgreSQL exception cho browser.
 * Raw code (PGRST116, 23505...) chỉ map nội bộ → message thân thiện.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ApiResponse
{
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok($data = null, string $message = 'OK', int $status = 200): void
    {
        self::send(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function fail(string $message, int $status = 400, ?array $errors = null): void
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        self::send($payload, $status);
    }

    /* ──────────────────────────────────────────────────────────────────
     * MAP LỖI SUPABASE → MESSAGE THÂN THIỆN
     * ────────────────────────────────────────────────────────────────── */

    /**
     * Chuyển kết quả lỗi của SupabaseClient thành response JSON thân thiện.
     * Raw code chỉ log server-side (SupabaseClient đã log), không lộ ra browser.
     */
    public static function fromSupabase(array $res, string $defaultMessage = 'Lỗi máy chủ, vui lòng thử lại'): void
    {
        $code = is_array($res['data'] ?? null) ? ($res['data']['code'] ?? null) : null;
        $status = (int)($res['status'] ?? 0);

        // Map PostgREST / PostgreSQL error codes
        switch ($code) {
            case 'PGRST116': // no rows
                self::fail('Không tìm thấy dữ liệu yêu cầu', 404);
                return;
            case '23505':    // unique_violation
                self::fail('Email đã được sử dụng.', 409);
                return;
            case '23503':    // foreign_key_violation
                self::fail('Dữ liệu tham chiếu không tồn tại hoặc đang được sử dụng.', 409);
                return;
            case 'PGRST204':
                self::fail('Không tìm thấy dữ liệu yêu cầu', 404);
                return;
        }

        if ($status === 404) {
            self::fail('Không tìm thấy dữ liệu yêu cầu', 404);
            return;
        }
        if ($status === 401) {
            self::fail('Phiên đăng nhập không hợp lệ', 401);
            return;
        }
        if ($status === 429) {
            self::fail('Quá nhiều yêu cầu, vui lòng thử lại sau', 429);
            return;
        }
        if ($status >= 500 || $status === 0) {
            self::fail($defaultMessage, 503);
            return;
        }

        self::fail($defaultMessage, $status >= 400 ? $status : 400);
    }

    /**
     * Message chuẩn cho lỗi booking trùng (concurrent overlap).
     */
    public static function bookingConflict(): void
    {
        self::fail(
            'Căn homestay vừa được đặt trong khoảng thời gian này. Vui lòng chọn ngày khác.',
            409
        );
    }
}
