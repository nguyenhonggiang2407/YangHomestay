<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * APPLY MIGRATION — chạy file SQL trên Supabase qua pg-meta /pg/query
 *
 * Dùng khi cần áp migration mới (006, 007...) lên Supabase đã import.
 * Secret key chỉ nằm trong header — KHÔNG bao giờ in ra.
 *
 * Chạy:  php tools/apply-migration.php database/supabase/006_booking_rpc.sql
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php apply-migration.php <path-to-sql>\n");
    exit(2);
}

$cfg = SupabaseClient::config();
$url = rtrim((string)$cfg['url'], '/');
$key = (string)$cfg['secret_key'];

$sql = file_get_contents($file);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Không đọc được file SQL.\n");
    exit(2);
}

$ch = curl_init($url . '/pg/query');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode(['query' => $sql], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$raw    = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$err    = curl_error($ch);
curl_close($ch);

echo 'HTTP ' . $status . PHP_EOL;

if ($err !== '') {
    fwrite(STDERR, "cURL error: " . $err . "\n");
    exit(1);
}
if ($status >= 200 && $status < 300) {
    echo "✅ Applied: " . basename($file) . "\n";
    exit(0);
}

$msg = json_decode($raw, true);
if (is_array($msg)) {
    // pg-meta trả {message, code} hoặc {details}
    if (isset($msg['message'])) {
        echo "❌ " . ($msg['message'] ?? '') . "\n";
        if (isset($msg['details']) && $msg['details'] !== '') {
            echo "  details: " . $msg['details'] . "\n";
        }
    } else {
        echo "❌ " . $raw . "\n";
    }
} else {
    echo "❌ " . $raw . "\n";
}
exit(1);
