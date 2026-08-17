<?php
declare(strict_types=1);

require_once __DIR__ . '/Session.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * CSRF — chống Cross-Site Request Forgery
 *
 * Flow:
 *   Session sinh token → frontend nhận token (qua API hoặc hidden field)
 *   → request POST/PATCH/DELETE kèm token (X-CSRF-Token header hoặc csrf_token)
 *   → backend verify bằng hash_equals
 *
 * Read-only GET KHÔNG yêu cầu CSRF.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class Csrf
{
    /**
     * Lấy (hoặc sinh) token CSRF của session hiện tại.
     */
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }

    /**
     * Verify token bằng hash_equals (chống timing attack).
     */
    public static function verify(?string $token): bool
    {
        Session::start();
        $expected = (string)($_SESSION['csrf_token'] ?? '');
        if ($expected === '' || $token === null || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /**
     * Verify từ request: ưu tiên header X-CSRF-Token, fallback field csrf_token.
     */
    public static function verifyRequest(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        return self::verify(is_string($token) ? $token : null);
    }

    /**
     * Hidden input cho form HTML.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
