<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * TEST — AVAILABILITY (validateDates + overlap convention)
 *
 * Chạy từ repo root: php backend/tests/test-availability.php
 * Không public production. Không chứa secret.
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../services/AvailabilityService.php'; // standalone (static validateDates)

$pass = 0;
$fail = 0;

function check(string $name, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✅ $name\n"; }
    else       { $fail++; echo "  ❌ $name\n"; }
}

echo "== 1. validateDates — format & thứ tự ==\n";
// Relative dates keep validation fixtures valid after the original demo dates pass.
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Ho_Chi_Minh'));
$checkIn = $today->modify('+30 days')->format('Y-m-d');
$checkOut = $today->modify('+33 days')->format('Y-m-d');
$past = $today->modify('-2 days')->format('Y-m-d');
$pastOut = $today->modify('-1 day')->format('Y-m-d');
check('ngày hợp lệ → null', AvailabilityService::validateDates($checkIn, $checkOut) === null);
check('sai format → lỗi', AvailabilityService::validateDates('not-a-date', $checkOut) !== null);
check('check_out <= check_in → lỗi', AvailabilityService::validateDates($checkIn, $checkIn) !== null);
check('check_out trước check_in → lỗi', AvailabilityService::validateDates($checkOut, $checkIn) !== null);
check('check_in quá khứ → lỗi', AvailabilityService::validateDates($past, $pastOut) !== null);

echo "\n== 2. Overlap convention (check_in inclusive / check_out exclusive) ==\n";
// Giả lập: booking hiện tại 20/08 → 23/08 (3 đêm: 20, 21, 22). Bán lại được từ 23/08.
$existingStart = '2026-08-20';
$existingEnd   = '2026-08-23';
$statusBlocks  = ['pending', 'confirmed', 'checkin']; // các status blocking

// overlap: new_check_in < existing_check_out AND new_check_out > existing_check_in
function overlaps(string $ni, string $no, string $ei, string $eo): bool
{
    return $ni < $eo && $no > $ei;
}

check('18 → 20 ✅ (check-out = check-in mới, không overlap)', !overlaps('2026-08-18', '2026-08-20', $existingStart, $existingEnd));
check('18 → 21 ❌ (vào trong)', overlaps('2026-08-18', '2026-08-21', $existingStart, $existingEnd));
check('20 → 22 ❌ (trùng trong)', overlaps('2026-08-20', '2026-08-22', $existingStart, $existingEnd));
check('22 → 24 ❌ (chạm cuối)', overlaps('2026-08-22', '2026-08-24', $existingStart, $existingEnd));
check('23 → 25 ✅ (bắt đầu đúng ngày check-out cũ)', !overlaps('2026-08-23', '2026-08-25', $existingStart, $existingEnd));
check('19 → 23 ❌ (chạm đầu)', overlaps('2026-08-19', '2026-08-23', $existingStart, $existingEnd));

echo "\n== 3. Status blocking ==\n";
check('pending block', in_array('pending', $statusBlocks, true));
check('confirmed block', in_array('confirmed', $statusBlocks, true));
check('checkin block', in_array('checkin', $statusBlocks, true));
check('checkout KHÔNG block', !in_array('checkout', $statusBlocks, true));
check('cancelled KHÔNG block', !in_array('cancelled', $statusBlocks, true));

echo "\n─── KẾT QUẢ: $pass pass / $fail fail ───\n";
exit($fail === 0 ? 0 : 1);
