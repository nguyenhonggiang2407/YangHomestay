<?php
declare(strict_types=1);

/**
 * CONCURRENCY TEST — 2 request gần như đồng thời cho CÙNG unit + CÙNG dates.
 * Yêu cầu: đúng 1 SUCCESS + 1 CONFLICT (409). Không bao giờ cả hai success.
 *
 * Chạy:  php tests/concurrency-test.php
 * Cách chạy 2 tiến trình song song (bash):
 *   php tests/concurrency-test.php --worker <customerId> <tag> &
 *   php tests/concurrency-test.php --worker <customerId> <tag> &
 *   wait
 */

require_once __DIR__ . '/../bootstrap.php';

$db = supabase();

$workerMode = in_array('--worker', $argv, true);

if ($workerMode) {
    $idx = array_search('--worker', $argv, true);
    $customerId = (int)($argv[$idx + 1] ?? 0);
    $tag = (string)($argv[$idx + 2] ?? '?');
    $res = (new BookingService())->create($customerId, [
        'unit_id'   => 13,
        'check_in'  => '2026-11-10',
        'check_out' => '2026-11-13',
        'adults'    => 2,
        'children'  => 0,
    ]);
    echo $tag . ': status=' . ($res['status'] ?? '?') . ' success=' . var_export(!empty($res['success']), true)
        . ' ref=' . ($res['data']['booking_reference'] ?? '') . ' msg=' . ($res['message'] ?? '') . "\n";
    exit(empty($res['success']) && ($res['status'] ?? 0) === 409 ? 0 : (empty($res['success']) ? 1 : 0));
}

/* ── Driver: tạo customer test → chạy 2 worker song song → kiểm tra ── */
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/BookingService.php';

$email = 'conc.' . time() . '@example.com';
$auth = new AuthService();
$reg = $auth->register(['full_name' => 'Concurrency Test', 'email' => $email, 'phone' => '0901234567', 'password' => 'ConcTest123!', 'confirm_password' => 'ConcTest123!']);
if (empty($reg['success'])) {
    fwrite(STDERR, "register failed: " . ($reg['message'] ?? '?') . "\n");
    exit(2);
}
$customerId = (int)($reg['data']['id'] ?? 0);
echo "customer_id=$customerId\n";

$php = PHP_BINARY;
$script = __FILE__;
$cmd = '"' . $php . '" "' . $script . '" --worker ' . $customerId . ' A & "'
     . $php . '" "' . $script . '" --worker ' . $customerId . ' B & wait';
exec($cmd, $out, $code);
echo implode("\n", $out) . "\n";

$results = array_values(array_filter($out, fn($l) => str_contains($l, 'status=')));
$success = 0; $conflict = 0;
foreach ($results as $line) {
    if (str_contains($line, 'success=true')) $success++;
    if (str_contains($line, 'status=409')) $conflict++;
}

/* Cleanup: xóa booking (cascade nights/items/usages) + customer */
$db->delete('bookings', Pg::eqInt('customer_id', $customerId));
$db->delete('customers', Pg::eqInt('id', $customerId));

$pass = $success === 1 && $conflict === 1;
echo "\nRESULT: success=$success conflict=$conflict → " . ($pass ? "PASS (đúng 1 thắng, 1 conflict)" : "FAIL") . "\n";
exit($pass ? 0 : 1);
