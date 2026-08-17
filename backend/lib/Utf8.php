<?php
declare(strict_types=1);

/**
 * UTF-8 compatibility helpers.
 * Shared hosting đôi khi không bật ext-mbstring. Các polyfill này chỉ được
 * khai báo khi PHP chưa có hàm native, nhờ đó code vẫn chạy được mà không
 * thay đổi hành vi trên máy chủ có mbstring.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        if ($string === '') return 0;
        $ok = preg_match_all('/./us', $string, $matches);
        return $ok === false ? strlen($string) : $ok;
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $length === null ? substr($string, $start) : substr($string, $start, $length);
        }
        $slice = $length === null ? array_slice($chars, $start) : array_slice($chars, $start, $length);
        return implode('', $slice);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        // Bổ sung bảng chữ cái Việt Nam; strtolower() xử lý ASCII còn lại.
        static $upper = [
            'A','À','Á','Ạ','Ả','Ã','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ',
            'Đ','E','È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
            'I','Ì','Í','Ị','Ỉ','Ĩ','O','Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
            'U','Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ','Y','Ỳ','Ý','Ỵ','Ỷ','Ỹ'
        ];
        static $lower = [
            'a','à','á','ạ','ả','ã','ă','ằ','ắ','ặ','ẳ','ẵ','â','ầ','ấ','ậ','ẩ','ẫ',
            'đ','e','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
            'i','ì','í','ị','ỉ','ĩ','o','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
            'u','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','y','ỳ','ý','ỵ','ỷ','ỹ'
        ];
        return strtolower(str_replace($upper, $lower, $string));
    }
}
