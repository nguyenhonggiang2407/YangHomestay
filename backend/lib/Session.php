<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * SESSION — khởi tạo session an toàn
 *
 * Cookie: HttpOnly, SameSite=Lax, Secure=true khi HTTPS (localhost HTTP → false).
 * Sau login phải gọi Session::regenerate() để chống session fixation.
 *
 * Context riêng:
 *   customer → $_SESSION['customer_id']  (authorization dựa vào id, KHÔNG dựa localStorage)
 *   admin    → $_SESSION['admin_id'] + $_SESSION['admin_role'] (role luôn đọc từ DB khi cần)
 * ═════════════════════════════════════════════════════════════════════════
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        session_set_cookie_params([
            'lifetime' => 0,                 // theo phiên
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,          // HTTPS production → true; localhost HTTP → false
            'httponly' => true,              // JS không đọc được cookie
            'samesite' => 'Lax',             // chống CSRF cơ bản
        ]);

        session_start();
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Chống session fixation — gọi sau khi đăng nhập thành công.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Xoá toàn bộ session + cookie.
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
