<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * VALIDATION — validate input backend, trả lỗi theo field (errors map)
 *
 * Controller bắt ValidationException → trả HTTP 422:
 *   { "success": false, "message": "...", "errors": { "email": "..." } }
 * ═════════════════════════════════════════════════════════════════════════
 */

class ValidationException extends Exception
{
    public array $errors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
}

final class Validation
{
    /**
     * Chuỗi bắt buộc, có giới hạn độ dài.
     */
    public static function string(string $key, $value, int $max = 255, bool $required = true, string $label = ''): string
    {
        $label = $label !== '' ? $label : $key;
        $value = trim((string)($value ?? ''));

        if ($value === '') {
            if ($required) {
                self::throwOne($key, 'Vui lòng nhập ' . $label . '.');
            }
            return '';
        }
        if (mb_strlen($value) > $max) {
            self::throwOne($key, $label . ' không được vượt quá ' . $max . ' ký tự.');
        }
        return $value;
    }

    public static function email(string $key, $value, bool $required = true): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($required) {
                self::throwOne($key, 'Vui lòng nhập email.');
            }
            return '';
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 100) {
            self::throwOne($key, 'Email không hợp lệ.');
        }
        return mb_strtolower($value);
    }

    /**
     * Số điện thoại Việt Nam: 0xxxxxxxxx hoặc +84xxxxxxxxx (9-10 số sau mã).
     */
    public static function phoneVN(string $key, $value, bool $required = true): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($required) {
                self::throwOne($key, 'Vui lòng nhập số điện thoại.');
            }
            return '';
        }
        if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', $value)) {
            self::throwOne($key, 'Số điện thoại không hợp lệ.');
        }
        return $value;
    }

    /**
     * Ngày định dạng Y-m-d hợp lệ.
     */
    public static function date(string $key, $value, bool $required = true): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            if ($required) {
                self::throwOne($key, 'Vui lòng nhập ngày.');
            }
            return '';
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            self::throwOne($key, 'Ngày không hợp lệ (định dạng YYYY-MM-DD).');
        }
        return $value;
    }

    public static function intInRange(string $key, $value, int $min, int $max, string $label = ''): int
    {
        $label = $label !== '' ? $label : $key;
        if (!is_numeric($value) || (int)$value != $value) {
            self::throwOne($key, $label . ' không hợp lệ.');
        }
        $n = (int)$value;
        if ($n < $min || $n > $max) {
            self::throwOne($key, $label . ' phải từ ' . $min . ' đến ' . $max . '.');
        }
        return $n;
    }

    /**
     * Số nguyên không âm (dùng cho id, tiền VND...).
     */
    public static function nonNegativeInt(string $key, $value, string $label = ''): int
    {
        $label = $label !== '' ? $label : $key;
        if (!is_numeric($value) || (int)$value != $value || (int)$value < 0) {
            self::throwOne($key, $label . ' không hợp lệ.');
        }
        return (int)$value;
    }

    public static function password(string $key, $value, int $min = 6): string
    {
        $value = (string)($value ?? '');
        if (strlen($value) < $min) {
            self::throwOne($key, 'Mật khẩu phải có ít nhất ' . $min . ' ký tự.');
        }
        if (strlen($value) > 72) { // bcrypt limit
            self::throwOne($key, 'Mật khẩu không được vượt quá 72 ký tự.');
        }
        return $value;
    }

    public static function boolean(string $key, $value): bool
    {
        return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
    }

    /**
     * Ném lỗi 1 field.
     */
    public static function throwOne(string $key, string $message): void
    {
        throw new ValidationException($message, [$key => $message]);
    }
}
