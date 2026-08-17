<?php
declare(strict_types=1);

require_once __DIR__ . '/Security.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * SUPABASE CLIENT — REST client cho Supabase Data API (PostgREST)
 *
 * Architecture:  Browser → PHP Backend → (cURL HTTPS 443) → Supabase REST → PostgreSQL
 *
 * • KHÔNG phụ thuộc pdo_pgsql / port 5432 — chạy được trên XAMPP, shared PHP hosting.
 * • Secret key chỉ nằm trong HEADER (apikey / Authorization), KHÔNG ở URL query.
 * • Không bao giờ log secret / Authorization header.
 * • Fail-safe: nếu chưa cấu hình URL + secret key → getInstance() trả null,
 *   controller phải trả lỗi dịch vụ thân thiện (503) mà không lộ chi tiết cấu hình.
 *
 * Method chính:
 *   select($table, $opts)   — GET, hỗ trợ select/filters/order/limit/offset/count
 *   insert($table, $payload)
 *   update($table, $payload, $filters)
 *   delete($table, $filters)
 *   rpc($fn, $params)       — POST /rest/v1/rpc/{fn}
 *
 * Mọi method trả về mảng chuẩn:
 *   ['ok' => bool, 'status' => int, 'data' => mixed, 'count' => ?int, 'headers' => [], 'error' => ?string]
 * ═════════════════════════════════════════════════════════════════════════
 */
final class SupabaseClient
{
    private string $baseUrl;
    private string $secretKey;
    private string $environment;
    private int $timeout;

    private static ?SupabaseClient $instance = null;
    private static ?array $config = null;

    private function __construct(array $config)
    {
        $this->baseUrl    = rtrim((string)$config['url'], '/');
        $this->secretKey  = (string)$config['secret_key'];
        $this->environment = (string)($config['environment'] ?? 'development');
        $this->timeout    = 15;
    }

    /* ──────────────────────────────────────────────────────────────────
     * CONFIG
     * ────────────────────────────────────────────────────────────────── */

    /**
     * Đọc config: ưu tiên config.supabase.local.php (gitignored),
     * fallback env vars SUPABASE_URL / SUPABASE_SECRET_KEY / APP_ENV.
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $cfg = [];
        $localFile = __DIR__ . '/../config.supabase.local.php';
        if (is_file($localFile)) {
            $loaded = require $localFile;
            if (is_array($loaded)) {
                $cfg = $loaded;
            }
        }

        $cfg['url']         = (string)($cfg['url'] ?? getenv('SUPABASE_URL') ?: '');
        $cfg['secret_key']  = (string)($cfg['secret_key'] ?? getenv('SUPABASE_SECRET_KEY') ?: '');
        $cfg['environment'] = (string)($cfg['environment'] ?? getenv('APP_ENV') ?: 'development');

        self::$config = $cfg;
        return $cfg;
    }

    /**
     * Kiểm tra cấu hình hợp lệ (URL thật + HTTPS + có secret key, không phải placeholder).
     */
    public static function isConfigured(): bool
    {
        $cfg = self::config();
        $url = trim($cfg['url'] ?? '');
        $key = trim($cfg['secret_key'] ?? '');
        if ($url === '' || $key === '') {
            return false;
        }
        if (str_contains($url, 'YOUR_PROJECT') || str_contains($url, 'placeholder')) {
            return false;
        }
        if (!str_starts_with($url, 'https://')) {
            return false; // bắt buộc HTTPS
        }
        return true;
    }

    /**
     * Singleton. Trả null nếu chưa cấu hình — controller phải fail-safe.
     */
    public static function getInstance(): ?SupabaseClient
    {
        if (!self::isConfigured()) {
            Security::logError('supabase', 'config_missing');
            return null;
        }
        if (self::$instance === null) {
            self::$instance = new self(self::config());
        }
        return self::$instance;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    /* ──────────────────────────────────────────────────────────────────
     * LOW-LEVEL REQUEST
     * ────────────────────────────────────────────────────────────────── */

    /**
     * Request HTTP tới Supabase REST API.
     * KHÔNG đưa key vào URL — key chỉ trong header.
     *
     * @return array{ok:bool,status:int,data:mixed,count:?int,headers:array,error:?string}
     */
    public function request(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        if (!function_exists('curl_init')) {
            Security::logError('supabase', 'curl_extension_missing', ['endpoint' => $this->sanitizePath($path)]);
            return ['ok' => false, 'status' => 0, 'data' => null, 'count' => null, 'headers' => [], 'error' => 'curl_missing'];
        }

        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $defaultHeaders = [
            'apikey: ' . $this->secretKey,
            'Authorization: Bearer ' . $this->secretKey,
            'Accept: application/json',
        ];
        if ($body !== null) {
            $defaultHeaders[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
            CURLOPT_HEADERFUNCTION => static function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $line = trim($header);
                if ($line !== '' && str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return $len;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        $errMsg = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // Network error — log nội bộ (không log secret), trả lỗi chung.
        if ($errno !== 0 || $raw === false) {
            Security::logError('supabase', 'network_error', [
                'errno'    => $errno,
                'endpoint' => $this->sanitizePath($path),
            ]);
            return ['ok' => false, 'status' => 0, 'data' => null, 'count' => null, 'headers' => [], 'error' => 'network'];
        }

        // 204 No Content — body rỗng hợp lệ.
        if ($status === 204) {
            return ['ok' => true, 'status' => 204, 'data' => null, 'count' => null, 'headers' => $responseHeaders, 'error' => null];
        }

        // Parse JSON an toàn — handle empty body / malformed JSON.
        $data = null;
        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Security::logError('supabase', 'bad_json', [
                    'status'   => $status,
                    'endpoint' => $this->sanitizePath($path),
                ]);
                return ['ok' => false, 'status' => $status, 'data' => null, 'count' => null, 'headers' => $responseHeaders, 'error' => 'parse'];
            }
            $data = $decoded;
        }

        // Content-Range: "0-11/97" → count=97 (dùng khi Prefer: count=exact)
        $count = null;
        if (isset($responseHeaders['content-range'])) {
            if (preg_match('#/(\d+)$#', $responseHeaders['content-range'], $m)) {
                $count = (int)$m[1];
            }
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'data' => $data, 'count' => $count, 'headers' => $responseHeaders, 'error' => null];
        }

        // Lỗi HTTP — giữ code/message để service map sang lỗi thân thiện.
        $code    = null;
        $message = null;
        if (is_array($data)) {
            $code    = $data['code'] ?? null;
            $message = $data['message'] ?? null;
        }

        Security::logError('supabase', 'http_error', [
            'status'   => $status,
            'pg_code'  => $code,
            'endpoint' => $this->sanitizePath($path),
        ]);

        return [
            'ok'      => false,
            'status'  => $status,
            'data'    => ['code' => $code, 'message' => $message],
            'count'   => null,
            'headers' => $responseHeaders,
            'error'   => 'http',
        ];
    }

    /** Chỉ giữ tên bảng/rpc trong log, bỏ toàn bộ query string (không chứa secret nhưng giữ log sạch). */
    private function sanitizePath(string $path): string
    {
        $path = preg_replace('/\?.*$/', '', $path);
        return substr((string)$path, 0, 200);
    }

    /* ──────────────────────────────────────────────────────────────────
     * HIGH-LEVEL: select / insert / update / delete / rpc
     * ────────────────────────────────────────────────────────────────── */

    /**
     * SELECT — GET /rest/v1/{table}
     *
     * $opts:
     *   'select'  => string  danh sách cột (whitelist ở service)
     *   'filters' => string|string[]  filter PostgREST dạng 'status=eq.active'
     *                (service phải validate/whitelist giá trị — KHÔNG nhận raw từ browser)
     *   'order'   => string  dạng 'base_price.asc' (whitelist ở service)
     *   'limit'   => int     (max 50 — service kiểm tra)
     *   'offset'  => int
     *   'count'   => bool    thêm Prefer: count=exact + Range → trả total qua 'count'
     */
    public function select(string $table, array $opts = []): array
    {
        $parts = [];

        if (!empty($opts['select'])) {
            $parts[] = 'select=' . rawurlencode((string)$opts['select']);
        }
        if (!empty($opts['filters'])) {
            foreach ((array)$opts['filters'] as $filter) {
                $parts[] = (string)$filter;
            }
        }
        if (!empty($opts['order'])) {
            $parts[] = 'order=' . rawurlencode((string)$opts['order']);
        }

        $limit  = isset($opts['limit']) ? (int)$opts['limit'] : 0;
        $offset = isset($opts['offset']) ? (int)$opts['offset'] : 0;
        $wantCount = !empty($opts['count']);

        $headers = [];
        if ($wantCount) {
            $headers[] = 'Prefer: count=exact';
            if ($limit > 0) {
                $headers[] = 'Range: ' . $offset . '-' . ($offset + $limit - 1);
            }
        } elseif ($limit > 0) {
            $parts[] = 'limit=' . $limit;
            if ($offset > 0) {
                $parts[] = 'offset=' . $offset;
            }
        }

        $path = '/rest/v1/' . $table . ($parts ? '?' . implode('&', $parts) : '');
        return $this->request('GET', $path, $headers);
    }

    /**
     * INSERT — POST /rest/v1/{table}. Trả về bản ghi đã tạo (return=representation).
     */
    public function insert(string $table, array $payload, array $opts = []): array
    {
        $path = '/rest/v1/' . $table;
        if (!empty($opts['select'])) {
            $path .= '?select=' . rawurlencode((string)$opts['select']);
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'count' => null, 'headers' => [], 'error' => 'encode'];
        }
        return $this->request('POST', $path, ['Prefer: return=representation'], $body);
    }

    /**
     * UPDATE — PATCH /rest/v1/{table}?{filters}. Filters do service xây dựng (đã validate).
     */
    public function update(string $table, array $payload, string $filters, array $opts = []): array
    {
        $path = '/rest/v1/' . $table . '?' . ltrim($filters, '?');
        if (!empty($opts['select'])) {
            $path .= '&select=' . rawurlencode((string)$opts['select']);
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'count' => null, 'headers' => [], 'error' => 'encode'];
        }
        return $this->request('PATCH', $path, ['Prefer: return=representation'], $body);
    }

    /**
     * DELETE — DELETE /rest/v1/{table}?{filters}. Filters do service xây dựng (đã validate).
     */
    public function delete(string $table, string $filters, array $opts = []): array
    {
        $path = '/rest/v1/' . $table . '?' . ltrim($filters, '?');
        if (!empty($opts['select'])) {
            $path .= '&select=' . rawurlencode((string)$opts['select']);
        }
        return $this->request('DELETE', $path, ['Prefer: return=representation']);
    }

    /**
     * RPC — POST /rest/v1/rpc/{fn}. Dùng cho is_unit_available() và create_booking_atomic().
     */
    public function rpc(string $function, array $params = []): array
    {
        $body = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'count' => null, 'headers' => [], 'error' => 'encode'];
        }
        return $this->request('POST', '/rest/v1/rpc/' . $function, [], $body);
    }
}
