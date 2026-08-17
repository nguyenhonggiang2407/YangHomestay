<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * PG — xây dựng filter PostgREST AN TOÀN
 *
 * KHÔNG đưa raw user string vào filter syntax. Mọi giá trị đi qua:
 *   • numeric → ép kiểu int
 *   • string  → rawurlencode (PostgREST decode lại)
 *   • ilike   → escape % _ * \ rồi bọc *term*
 *
 * Quy ước lọc: giá trị do service validate/whitelist trước khi gọi.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class Pg
{
    /** col=eq.123 (int) */
    public static function eqInt(string $col, $val): string
    {
        return $col . '=eq.' . (int)$val;
    }

    /** col=neq.123 */
    public static function neqInt(string $col, $val): string
    {
        return $col . '=neq.' . (int)$val;
    }

    /** col=eq.'chuỗi' (đã encode) */
    public static function eqStr(string $col, string $val): string
    {
        return $col . '=eq.' . rawurlencode($val);
    }

    /** col=in.(1,2,3) */
    public static function inInts(string $col, array $ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($n) => $n > 0));
        return $col . '=in.(' . implode(',', $ids) . ')';
    }

    /** col=gte.1000 / col=lte.5000 */
    public static function gteInt(string $col, $val): string
    {
        return $col . '=gte.' . (int)$val;
    }

    public static function lteInt(string $col, $val): string
    {
        return $col . '=lte.' . (int)$val;
    }

    /** col=is.null */
    public static function isNull(string $col): string
    {
        return $col . '=is.null';
    }

    /**
     * Dùng BÊN TRONG or=(...) — cú pháp dot: col.eq.giá-trị.
     * PostgREST logic tree KHÔNG chấp nhận dạng col=eq.giá-trị (lỗi PGRST100).
     */
    public static function orEqStr(string $col, string $val): string
    {
        return $col . '.eq.' . rawurlencode($val);
    }

    /** col=ilike.*term* — escape ký tự đặc biệt PostgREST */
    public static function like(string $col, string $term): string
    {
        $escaped = str_replace(['\\', '%', '_', '*'], ['\\\\', '\\%', '\\_', '\\*'], trim($term));
        return $col . '=ilike.*' . rawurlencode($escaped) . '*';
    }

    /** timestamp ISO (UTC) cho filter created_at=gte.... */
    public static function isoTime(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /** order=col.desc */
    public static function order(string $col, string $dir = 'asc'): string
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        return 'order=' . $col . '.' . $dir;
    }

    /** Lọc giá trị enum an toàn: chỉ cho phép trong danh sách whitelist, ngược lại default. */
    public static function whitelist($value, array $allowed, $default = null)
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
