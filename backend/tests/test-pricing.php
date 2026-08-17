<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * TEST — PRICING ENGINE (pure logic, KHÔNG cần Supabase)
 *
 * Chạy:  php tests/test-pricing.php
 * Không public production. Không chứa secret.
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../services/PromotionService.php'; // static computeDiscount
require_once __DIR__ . '/../services/PricingService.php'; // standalone (chỉ dùng static)

$pass = 0;
$fail = 0;

function check(string $name, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✅ $name\n"; }
    else       { $fail++; echo "  ❌ $name\n"; }
}

$unit = [
    'id'           => 1,
    'name'         => 'Test Studio',
    'base_price'   => 850000,
    'cleaning_fee' => 100000,
    'max_guests'   => 3,
];

echo "== 1. Base price — không có rate rule ==\n";
$q = PricingService::computeQuote($unit, [], '2026-08-20', '2026-08-23');
check('3 đêm', $q['number_of_nights'] === 3);
check('subtotal = 850k×3 = 2.550.000', $q['subtotal'] === 2550000);
check('cleaning = 100.000', $q['cleaning_fee'] === 100000);
check('total = 2.650.000', $q['total_amount'] === 2650000);
check('không có discount', $q['discount_amount'] === 0);
check('currency = VND', $q['currency'] === 'VND');

echo "== 2. Rate rule cuối tuần (day_of_week) ==\n";
// 2026-08-22 là thứ 7 (dow 6 → stored 7). Rule cho T7.
$rules = [
    ['id' => 1, 'name' => 'Weekend Sat', 'start_date' => null, 'end_date' => null, 'day_of_week' => 7, 'price' => 1050000, 'priority' => 5, 'active' => true, 'updated_at' => '2026-01-01'],
];
$q = PricingService::computeQuote($unit, $rules, '2026-08-21', '2026-08-24');
check('3 đêm', $q['number_of_nights'] === 3);
check('đêm 21/08 (T6) = base 850k', $q['nights'][0]['rate'] === 850000);
check('đêm 22/08 (T7) = rule 1.050k', $q['nights'][1]['rate'] === 1050000);
check('đêm 23/08 (CN) = base 850k', $q['nights'][2]['rate'] === 850000);
check('subtotal = 850+1050+850 = 2.750.000', $q['subtotal'] === 2750000);

echo "== 3. Rule theo khoảng ngày (seasonal) + priority ==\n";
$rules = [
    ['id' => 1, 'name' => 'Low priority date range', 'start_date' => '2026-08-01', 'end_date' => '2026-09-01', 'day_of_week' => null, 'price' => 900000, 'priority' => 1, 'active' => true, 'updated_at' => '2026-01-01'],
    ['id' => 2, 'name' => 'High priority weekend', 'start_date' => null, 'end_date' => null, 'day_of_week' => 6, 'price' => 1200000, 'priority' => 10, 'active' => true, 'updated_at' => '2026-01-01'],
];
$q = PricingService::computeQuote($unit, $rules, '2026-08-20', '2026-08-23');
check('đêm 20/08 (T5): seasonal 900k (không phải T6)', $q['nights'][0]['rate'] === 900000);
check('đêm 21/08 (T6): weekend 1.2M (priority cao thắng)', $q['nights'][1]['rate'] === 1200000);
check('đêm 22/08 (T7): seasonal 900k', $q['nights'][2]['rate'] === 900000);

echo "== 4. Rule không active bị bỏ qua ==\n";
$rules = [
    ['id' => 1, 'name' => 'Inactive weekend', 'start_date' => null, 'end_date' => null, 'day_of_week' => 7, 'price' => 9999999, 'priority' => 99, 'active' => false, 'updated_at' => '2026-01-01'],
];
$q = PricingService::computeQuote($unit, $rules, '2026-08-21', '2026-08-24');
check('đêm T7 vẫn = base (rule inactive)', $q['nights'][1]['rate'] === 850000);

echo "== 5. Promotion percentage (10%) + cap ==\n";
$promoPct = ['id' => 1, 'code' => 'YANGNEW10', 'discount_type' => 'percentage', 'discount_value' => 10, 'maximum_discount' => null];
$q = PricingService::computeQuote($unit, [], '2026-08-20', '2026-08-23', $promoPct);
check('discount = 10% × 2.550.000 = 255.000', $q['discount_amount'] === 255000);
check('total = 2.550.000 + 100.000 − 255.000 = 2.395.000', $q['total_amount'] === 2395000);

$promoCap = ['id' => 2, 'code' => 'CAP', 'discount_type' => 'percentage', 'discount_value' => 50, 'maximum_discount' => 200000];
$q = PricingService::computeQuote($unit, [], '2026-08-20', '2026-08-23', $promoCap);
check('50% nhưng cap 200k → discount = 200k', $q['discount_amount'] === 200000);

echo "== 6. Promotion fixed amount + không âm ==\n";
$promoFixed = ['id' => 3, 'code' => 'HANOI150', 'discount_type' => 'fixed_amount', 'discount_value' => 150000, 'maximum_discount' => null];
$q = PricingService::computeQuote($unit, [], '2026-08-20', '2026-08-23', $promoFixed);
check('fixed 150.000', $q['discount_amount'] === 150000);
check('total = 2.550.000 + 100.000 − 150.000 = 2.500.000', $q['total_amount'] === 2500000);

// discount vượt total → total = 0 (không âm)
$promoHuge = ['id' => 4, 'code' => 'HUGE', 'discount_type' => 'fixed_amount', 'discount_value' => 99999999, 'maximum_discount' => null];
$q = PricingService::computeQuote(['id' => 1, 'name' => 'x', 'base_price' => 100000, 'cleaning_fee' => 0, 'max_guests' => 2], [], '2026-08-20', '2026-08-21', $promoHuge);
check('total không âm', $q['total_amount'] === 0);

echo "== 7. formatVnd ==\n";
check('850.000 ₫', PricingService::formatVnd(850000) === '850.000 ₫');
check('1.200.000 ₫', PricingService::formatVnd(1200000) === '1.200.000 ₫');

echo "\n─── KẾT QUẢ: $pass pass / $fail fail ───\n";
exit($fail === 0 ? 0 : 1);
