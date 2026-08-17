<?php
declare(strict_types=1);

/**
 * ═════════════════════════════════════════════════════════════════════════
 * LIVE TEST — toàn bộ stack YangHomestay chạy trên Supabase THẬT
 *
 * Chạy:  php tests/live-test.php
 *
 * • Test qua các Service (cùng code router gọi) — không cần HTTP server.
 * • Tạo test customer + booking, SAU ĐÓ DỌN: xóa booking (cascade nights/
 *   items/usages) rồi xóa customer. Không phá seed.
 * • KHÔNG bao giờ in/log secret key.
 * ═════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../bootstrap.php';

// Services (router cũng require theo cách này)
foreach (glob(__DIR__ . '/../services/*.php') as $svcFile) {
    require_once $svcFile;
}

$PASS = 0; $FAIL = 0; $FAILED_NAMES = [];

function check(string $name, $cond, string $detail = ''): void
{
    global $PASS, $FAIL, $FAILED_NAMES;
    if ((bool)$cond) { $PASS++; echo "  ✅ $name\n"; }
    else {
        $FAIL++;
        $FAILED_NAMES[] = $name;
        echo "  ❌ $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function section(string $title): void { echo "\n== $title ==\n"; }

$db = supabase(); // fail-safe: thiếu config → 500

/* ═════════════════════════════════════════════════════════════════════════
   1. SEED DATA VERIFICATION
   ═════════════════════════════════════════════════════════════════════════ */
section('1. Seed data (Supabase)');
$counts = [];
foreach (['properties','units','amenities','experience_tags','bookings','reviews','promotions','customers','admins','availability_blocks','rate_rules'] as $t) {
    $r = $db->select($t, ['select'=>'id','count'=>true,'limit'=>1]);
    $counts[$t] = $r['count'];
}
check('properties = 5', $counts['properties'] === 5, (string)$counts['properties']);
check('units = 16', $counts['units'] === 16, (string)$counts['units']);
check('amenities = 18', $counts['amenities'] === 18, (string)$counts['amenities']);
check('experience_tags = 19', $counts['experience_tags'] === 19, (string)$counts['experience_tags']);
$baselineBookings = (int)($counts['bookings'] ?? 12); // 12 seed + booking thật từ người dùng (nếu có)
check('bookings = baseline (seed 12 + thật)', $counts['bookings'] >= 12, (string)$counts['bookings']);
check('reviews = 5', $counts['reviews'] === 5, (string)$counts['reviews']);
check('promotions = 2', $counts['promotions'] === 2, (string)$counts['promotions']);

/* ═════════════════════════════════════════════════════════════════════════
   2. PROPERTY / UNIT (public)
   ═════════════════════════════════════════════════════════════════════════ */
section('2. Property & Unit API');
$props = (new PropertyService())->list([], 1, 12);
check('property list success', !empty($props['success']));
check('property list has items', count($props['data']['items'] ?? []) === 5, 'count=' . count($props['data']['items'] ?? []));
$firstProp = $props['data']['items'][0] ?? null;
check('property has cover_image', !empty($firstProp['cover_image']));
check('property has unit_count', isset($firstProp['unit_count']));

$detail = (new PropertyService())->detail((int)$firstProp['id']);
check('property detail success', !empty($detail['success']));
check('property detail units non-empty', count($detail['data']['units'] ?? []) > 0);
check('property detail has nearby', is_array($detail['data']['property']['nearby_places'] ?? null));

$units = (new UnitService())->list([], 1, 12);
check('unit list success', !empty($units['success']));
check('unit list page 1 = 12 items (limit)', count($units['data']['items'] ?? []) === 12, 'count=' . count($units['data']['items'] ?? []));
check('pagination total = 16', ($units['data']['pagination']['total'] ?? 0) === 16);

$ud = (new UnitService())->detail((int)$units['data']['items'][0]['id']);
check('unit detail success', !empty($ud['success']));
check('unit detail has property', !empty($ud['data']['property']));
check('unit detail has amenities', is_array($ud['data']['amenities'] ?? null));
check('unit detail has reviews', is_array($ud['data']['reviews'] ?? null));

// filters
$f = (new UnitService())->list(['district' => 'Tây Hồ'], 1, 12);
check('filter district=Tây Hồ', count($f['data']['items'] ?? []) > 0);
$g = (new UnitService())->list(['guests' => 4], 1, 12);
$allFit = true;
foreach (($g['data']['items'] ?? []) as $u) { if ((int)$u['max_guests'] < 4) $allFit = false; }
check('filter guests>=4', $allFit);
$p = (new UnitService())->list(['min_price' => 2000000, 'max_price' => 3000000], 1, 12);
$allPrice = true;
foreach (($p['data']['items'] ?? []) as $u) { if ((int)$u['base_price'] < 2000000 || (int)$u['base_price'] > 3000000) $allPrice = false; }
check('filter price 2tr-3tr', $allPrice);
$s = (new UnitService())->list(['search' => 'Phố Cổ'], 1, 12);
check('search "Phố Cổ" hits', count($s['data']['items'] ?? []) > 0);
$page2 = (new UnitService())->list([], 2, 12);
check('pagination page 2 = 4 items', count($page2['data']['items'] ?? []) === 4, 'count=' . count($page2['data']['items'] ?? []));
$sort = (new UnitService())->list(['sort' => 'price_desc'], 1, 12);
$firstPrice = (int)($sort['data']['items'][0]['base_price'] ?? 0);
$lastPrice  = (int)($sort['data']['items'][count($sort['data']['items']) - 1]['base_price'] ?? 0);
check('sort price_desc', $firstPrice >= $lastPrice);

/* ═════════════════════════════════════════════════════════════════════════
   3. AVAILABILITY
   Đơn đặt seed: unit 13 (Cau Giay Modern) confirmed 20/08→22/08 (blocking).
   Block seed:   unit 11 (Dong Da Urban)  maintenance 20/08→23/08.
   ═════════════════════════════════════════════════════════════════════════ */
section('3. Availability (unit 13 có booking 20→22/08)');
$av = new AvailabilityService();
check('18→20 free (boundary check-in = check-out cũ)', ($av->check(13, '2026-08-18', '2026-08-20')['data']['available'] ?? false) === true);
check('18→21 partial overlap → blocked', ($av->check(13, '2026-08-18', '2026-08-21')['data']['available'] ?? true) === false);
check('20→22 full overlap → blocked', ($av->check(13, '2026-08-20', '2026-08-22')['data']['available'] ?? true) === false);
check('21→23 overlap → blocked', ($av->check(13, '2026-08-21', '2026-08-23')['data']['available'] ?? true) === false);
check('22→24 free (check-out boundary)', ($av->check(13, '2026-08-22', '2026-08-24')['data']['available'] ?? false) === true);

section('3b. Availability blocks (unit 11 bị chặn 20→23/08)');
check('19→21 trong block → blocked', ($av->check(11, '2026-08-19', '2026-08-21')['data']['available'] ?? true) === false);
check('23→25 sau block → free (end exclusive)', ($av->check(11, '2026-08-23', '2026-08-25')['data']['available'] ?? false) === true);
check('25→27 hoàn toàn sau → free', ($av->check(11, '2026-08-25', '2026-08-27')['data']['available'] ?? false) === true);

section('3c. Date validation');
check('check_out <= check_in → 422', ($av->check(13, '2026-08-20', '2026-08-20')['status'] ?? 0) === 422);
check('check_in quá khứ → 422', ($av->check(13, '2020-01-01', '2020-01-03')['status'] ?? 0) === 422);

/* ═════════════════════════════════════════════════════════════════════════
   4. PRICING & PROMOTION (booking-quote)
   Unit 13: base 750.000, cleaning 80.000, rule T6 = 800.000 (id 3).
   2026-08-21 là Thứ 6 → đêm đó dùng rule 800k.
   ═════════════════════════════════════════════════════════════════════════ */
section('4. Pricing (unit 13: base 700k, cleaning 80k, rule T6=800k; quote 21→24/08)');
$q = (new PricingService())->quote(13, '2026-08-21', '2026-08-24');
check('quote success', !empty($q['success']));
$d = $q['data'];
check('3 đêm', $d['number_of_nights'] === 3);
check('đêm 21/08 (T6) = rule 800k', $d['nights'][0]['rate'] === 800000, 'rate=' . ($d['nights'][0]['rate'] ?? '?'));
check('đêm 22/08 (T7) = base 700k', $d['nights'][1]['rate'] === 700000, 'rate=' . ($d['nights'][1]['rate'] ?? '?'));
check('đêm 23/08 (CN) = base 700k', $d['nights'][2]['rate'] === 700000, 'rate=' . ($d['nights'][2]['rate'] ?? '?'));
check('subtotal = 800+700+700 = 2.200.000', $d['subtotal'] === 2200000, 'subtotal=' . ($d['subtotal'] ?? '?'));
check('cleaning fee = 80.000', $d['cleaning_fee'] === 80000);
check('total = 2.280.000', $d['total_amount'] === 2280000, 'total=' . ($d['total_amount'] ?? '?'));

section('4b. Promotions');
$qPct = (new PricingService())->quote(13, '2026-08-21', '2026-08-24', 'YANGNEW10');
check('YANGNEW10 10% → discount = 220.000', ($qPct['data']['discount_amount'] ?? 0) === 220000, 'discount=' . ($qPct['data']['discount_amount'] ?? '?'));
check('YANGNEW10 total = 2.280.000 − 220.000 = 2.060.000', ($qPct['data']['total_amount'] ?? 0) === 2060000, 'total=' . ($qPct['data']['total_amount'] ?? '?'));

$qFix = (new PricingService())->quote(13, '2026-08-21', '2026-08-24', 'HANOI150');
check('HANOI150 fixed 150k', ($qFix['data']['discount_amount'] ?? 0) === 150000);

$qBad = (new PricingService())->quote(13, '2026-08-21', '2026-08-24', 'KHONG_TON_TAI');
check('promo không tồn tại → fail', empty($qBad['success']));
check('promo không tồn tại → 422', ($qBad['status'] ?? 0) === 422);

/* ═════════════════════════════════════════════════════════════════════════
   5. CUSTOMER AUTH (tạo customer TEST, dọn sau)
   ═════════════════════════════════════════════════════════════════════════ */
section('5. Customer auth');
// Reset login throttle (dev test only): suite chạy lặp từ cùng IP ('cli' trong CLI)
// → xóa failed attempts do suite tạo để không vấp 429 giữa các lần chạy.
// Chỉ xóa log THẤT BẠI cùng IP máy dev — không xóa log thành công.
$testIp = Security::clientIp();
if ($testIp !== '') {
    $db->delete('login_logs', 'success=eq.false&ip_address=eq.' . rawurlencode($testIp));
}
// Self-healing: dọn customer test còn sót từ lần chạy trước bị crash (namespace live.test.%)
$leftovers = $db->select('customers', ['select' => 'id', 'filters' => Pg::like('email', 'live.test.'), 'limit' => 100]);
foreach (($leftovers['data'] ?? []) as $lc) {
    $lcid = (int)$lc['id'];
    $db->delete('reviews', Pg::eqInt('customer_id', $lcid));
    $db->delete('bookings', Pg::eqInt('customer_id', $lcid));
    $db->delete('customers', Pg::eqInt('id', $lcid));
}
$db->delete('contact_messages', Pg::like('email', 'live.test.'));

// Baseline customers (có thể có user đăng ký thật trên site — không xóa, chỉ đếm)
$baseCust = $db->select('customers', ['select' => 'id', 'count' => true, 'limit' => 1]);
$baselineCustomers = (int)($baseCust['count'] ?? 8);

$testEmail = 'live.test.' . time() . '@example.com';
$testPwd = 'TestPass123!';

$auth = new AuthService();
$extraCustomers = 0;  // test customer đang tồn tại (chưa cleanup)
$reg = $auth->register(['full_name' => 'Live Test User', 'email' => $testEmail, 'phone' => '0901234567', 'password' => $testPwd, 'confirm_password' => $testPwd]);
check('register success', !empty($reg['success']), $reg['message'] ?? '');
if (!empty($reg['success'])) { $extraCustomers++; }

$dup = $auth->register(['full_name' => 'X', 'email' => $testEmail, 'phone' => '0912345678', 'password' => 'Another123!', 'confirm_password' => 'Another123!']);
check('duplicate email → 409', ($dup['status'] ?? 0) === 409);

$bad = $auth->login($testEmail, 'WrongPass123!');
check('wrong password → 401', ($bad['status'] ?? 0) === 401);

$login = $auth->login($testEmail, $testPwd);
check('login success', !empty($login['success']));
check('login không trả password_hash', !isset($login['data']['password_hash']));

$authOk = $auth->checkAuth();
check('check-auth authenticated', !empty($authOk['authenticated']));
check('check-auth data id', ($authOk['data']['id'] ?? 0) > 0);
check('check-auth session persistence (cùng process)', (int)AuthService::currentId() === (int)$authOk['data']['id']);

$prof = $auth->getProfile();
check('get-profile success', !empty($prof['success']));
check('get-profile không trả password_hash', !isset($prof['data']['password_hash']));

$upd = $auth->updateProfile(['full_name' => 'Live Test Renamed', 'phone' => '0901234567']);
check('update-profile success', !empty($upd['success']));

$pwBad = $auth->changePassword(['old_password' => 'WrongOld!', 'new_password' => 'NewPass123!', 'confirm_password' => 'NewPass123!']);
check('change-password sai mật khẩu cũ → 400', ($pwBad['status'] ?? 0) === 400);

$pwOk = $auth->changePassword(['old_password' => $testPwd, 'new_password' => 'NewPass123!', 'confirm_password' => 'NewPass123!']);
check('change-password success', !empty($pwOk['success']));

$relog = $auth->login($testEmail, 'NewPass123!');
check('login với mật khẩu mới', !empty($relog['success']));

$customerId = (int)AuthService::currentId();

/* ═════════════════════════════════════════════════════════════════════════
   Kiểm tra RPC create_booking_atomic có tồn tại trên Supabase chưa
   (006_booking_rpc.sql phải được chạy trong SQL Editor).
   ═════════════════════════════════════════════════════════════════════════ */
// Probe KHÔNG gây side effect: check_in quá khứ → RPC trả lỗi validation trước khi insert.
// - Function tồn tại  → HTTP 400 (YB002) → rpcAvailable = true
// - Function chưa có  → HTTP 404 (PGRST202) → rpcAvailable = false
$rpcProbe = $db->rpc('create_booking_atomic', ['p_customer_id'=>1,'p_unit_id'=>13,'p_check_in'=>'2020-01-01','p_check_out'=>'2020-01-03']);
$rpcAvailable = ($rpcProbe['status'] ?? 0) !== 404;
$extraBookings = 0;   // test bookings đang tồn tại trong DB (assert admin counts)

/* ═════════════════════════════════════════════════════════════════════════
   6. BOOKING (atomic RPC) — unit 13, 10→13/09 (chưa bị chặn)
   ═════════════════════════════════════════════════════════════════════════ */
section('6. Atomic booking (unit 13, 10→13/09)');
$bs = new BookingService();
if (!$rpcAvailable) {
    echo "  ⚠️ SKIP — create_booking_atomic chưa tồn tại trên Supabase (cần chạy 006_booking_rpc.sql trong SQL Editor)\n";
} else {

// quote trước (source of truth backend)
$quote = (new PricingService())->quote(13, '2026-09-10', '2026-09-13');
check('quote trước booking thành công', !empty($quote['success']));
$expectedTotal = $quote['data']['total_amount'] ?? 0;

$bk = $bs->create($customerId, ['unit_id' => 13, 'check_in' => '2026-09-10', 'check_out' => '2026-09-13', 'adults' => 2, 'children' => 0, 'special_requests' => 'Live test']);
check('create booking success', !empty($bk['success']), $bk['message'] ?? '');
$booking = $bk['data'] ?? [];
check('có booking_reference (YH...)', preg_match('/^YH\d{8}\d{4}$/', (string)($booking['booking_reference'] ?? '')), (string)($booking['booking_reference'] ?? ''));
check('status = pending', ($booking['status'] ?? '') === 'pending');
check('payment_status = unpaid', ($booking['payment_status'] ?? '') === 'unpaid');
check('3 đêm', ($booking['number_of_nights'] ?? 0) === 3);
check('total khớp quote backend', (int)($booking['total_amount'] ?? 0) === (int)$expectedTotal, 'booking=' . ($booking['total_amount'] ?? '?') . ' quote=' . $expectedTotal);
$bookingId = (int)($booking['id'] ?? 0);
if ($bookingId > 0) { $extraBookings++; }

// verify snapshot trong DB
$nights = $db->select('booking_nights', ['select'=>'stay_date,nightly_rate', 'filters'=>Pg::eqInt('booking_id', $bookingId), 'order'=>'stay_date.asc', 'limit'=>10]);
check('booking_nights có 3 dòng', count($nights['data'] ?? []) === 3, 'count=' . count($nights['data'] ?? []));
$sumNights = array_sum(array_map(fn($n) => (int)$n['nightly_rate'], $nights['data'] ?? []));
check('Σ nights = subtotal', $sumNights === (int)($booking['subtotal'] ?? 0), "sum=$sumNights subtotal=" . ($booking['subtotal'] ?? '?'));

$items = $db->select('booking_price_items', ['select'=>'item_type,label,amount', 'filters'=>Pg::eqInt('booking_id', $bookingId), 'order'=>'display_order.asc', 'limit'=>10]);
check('booking_price_items có dòng', count($items['data'] ?? []) > 0);
check('price items có accommodation', in_array('accommodation', array_column($items['data'] ?? [], 'item_type'), true));
check('price items có cleaning_fee', in_array('cleaning_fee', array_column($items['data'] ?? [], 'item_type'), true));

// CONFLICT — cùng unit, dates chồng lấn
$conflict = $bs->create($customerId, ['unit_id' => 13, 'check_in' => '2026-09-11', 'check_out' => '2026-09-14', 'adults' => 1, 'children' => 0]);
check('concurrent overlap → 409', ($conflict['status'] ?? 0) === 409, $conflict['message'] ?? '');
check('conflict message thân thiện', str_contains($conflict['message'] ?? '', 'vừa được đặt'));

// guests vượt sức chứa
$tooMany = $bs->create($customerId, ['unit_id' => 13, 'check_in' => '2026-09-20', 'check_out' => '2026-09-22', 'adults' => 9, 'children' => 0]);
check('vượt sức chứa → 422', ($tooMany['status'] ?? 0) === 422);
}

/* ═════════════════════════════════════════════════════════════════════════
   7. MY BOOKINGS / DETAIL / CANCEL
   ═════════════════════════════════════════════════════════════════════════ */
section('7. My bookings & cancel');
if (!$rpcAvailable) {
    echo "  ⚠️ SKIP — cần booking để test (chạy 006_booking_rpc.sql trước)\n";
} else {
$mine = $bs->myBookings($customerId);
check('my-bookings có booking mới', count($mine['data'] ?? []) >= 1);
$mineBooking = null;
foreach (($mine['data'] ?? []) as $b) { if ((int)$b['id'] === $bookingId) $mineBooking = $b; }
check('booking mới nằm trong danh sách', $mineBooking !== null);
check('my-bookings enrich unit', !empty($mineBooking['unit']['name']));

$bd = $bs->detail($customerId, $bookingId);
check('booking-detail success', !empty($bd['success']));
check('booking-detail có nights', count($bd['data']['nights'] ?? []) === 3);
check('booking-detail có price_items', count($bd['data']['price_items'] ?? []) >= 2);

$other = $bs->detail(1, $bookingId); // customer khác (seed customer 1)
check('customer khác KHÔNG xem được booking → 404', ($other['status'] ?? 0) === 404);

$can = $bs->cancel($customerId, $bookingId, 'Live test cleanup');
check('cancel success', !empty($can['success']));
$afterCancel = $db->select('bookings', ['select'=>'status,cancelled_at', 'filters'=>Pg::eqInt('id', $bookingId), 'limit'=>1]);
check('status = cancelled sau cancel', ($afterCancel['data'][0]['status'] ?? '') === 'cancelled');

$can2 = $bs->cancel($customerId, $bookingId);
check('cancel lần 2 (cancelled→cancelled) → 422', ($can2['status'] ?? 0) === 422);
}

/* ═════════════════════════════════════════════════════════════════════════
   8. ADMIN (login + các module)
   ═════════════════════════════════════════════════════════════════════════ */
section('8. Admin');
$adminAuth = new AdminAuthService();
$aBad = $adminAuth->login('admin', 'WrongPass123!');
check('admin sai mật khẩu → 401', ($aBad['status'] ?? 0) === 401);
$aLogin = $adminAuth->login('admin', 'admin123');
check('admin login success', !empty($aLogin['success']));
check('admin role từ DB', in_array($aLogin['data']['role'] ?? '', ['super_admin', 'manager', 'staff'], true));
$aCheck = $adminAuth->checkAuth();
check('admin check-auth', !empty($aCheck['authenticated']));
check('admin không trả password_hash', !isset($aCheck['data']['password_hash']));

$abs = new AdminBookingService();
$dash = $abs->dashboard();
check('dashboard success', !empty($dash['success']));
check('dashboard properties_total = 5', ($dash['data']['properties_total'] ?? 0) === 5);
check('dashboard units_total = 16', ($dash['data']['units_total'] ?? 0) === 16);
check('dashboard bookings_by_status tổng = baseline (+test)', array_sum($dash['data']['bookings_by_status'] ?? []) === $baselineBookings + $extraBookings, 'sum=' . array_sum($dash['data']['bookings_by_status'] ?? []) . ' baseline=' . ($baselineBookings + $extraBookings));
check('dashboard có revenue_month', isset($dash['data']['revenue_month']));
check('dashboard có revenue_6m', is_array($dash['data']['revenue_6m'] ?? null));

$cal = $abs->calendar('2026-08-01', '2026-09-30');
check('calendar success', !empty($cal['success']));
check('calendar có bookings', count($cal['data']['bookings'] ?? []) > 0);
check('calendar có blocks', count($cal['data']['blocks'] ?? []) === 3);

$bl = $abs->list([], 1, 20);
check('admin bookings list = baseline (+test)', count($bl['data']['items'] ?? []) === $baselineBookings + $extraBookings, 'count=' . count($bl['data']['items'] ?? []) . ' baseline=' . ($baselineBookings + $extraBookings));
check('admin bookings enrich unit', !empty($bl['data']['items'][0]['unit']['name']));

$rev = $abs->revenueReport();
check('revenue report success', !empty($rev['success']));
check('revenue monthly 12 tháng', count($rev['data']['monthly'] ?? []) === 12);
check('revenue paid_total > 0', ($rev['data']['paid_total'] ?? 0) > 0);

$cust = (new AdminCustomerService())->list([], 1, 20);
check('admin customers = baseline (+test)', count($cust['data']['items'] ?? []) === $baselineCustomers + $extraCustomers, 'count=' . count($cust['data']['items'] ?? []) . ' baseline=' . $baselineCustomers);
check('customer có booking_count', isset($cust['data']['items'][0]['booking_count']));

$reviews = (new ReviewService())->adminList();
check('admin reviews = 5', count($reviews['data'] ?? []) === 5, 'count=' . count($reviews['data'] ?? []));

$rr = (new AdminCatalogService())->rateRuleList();
check('rate rules = 7', count($rr['data']['items'] ?? []) === 7);
check('rate rule có unit_name', !empty($rr['data']['items'][0]['unit_name']));

$promos = (new AdminCatalogService())->promotionList();
check('promotions = 2', count($promos['data']['items'] ?? []) === 2);

$props = (new AdminCatalogService())->propertyList(1, 20);
check('admin properties = 5', count($props['data']['items'] ?? []) === 5);

$unitList = (new AdminCatalogService())->unitList();
check('admin units = 16', count($unitList['data']['items'] ?? []) === 16);

// booking state machine: tạo booking mới → confirmed → checkin → checkout
if (!$rpcAvailable) {
    echo "  ⚠️ SKIP — state machine cần booking (chạy 006_booking_rpc.sql trước)\n";
} else {
$bk2 = $bs->create($customerId, ['unit_id' => 13, 'check_in' => '2026-09-25', 'check_out' => '2026-09-28', 'adults' => 2, 'children' => 0]);
$bk2Id = (int)($bk2['data']['id'] ?? 0);
check('state machine: booking 2 created', $bk2Id > 0);
check('state machine: pending → confirmed', !empty($abs->updateStatus($bk2Id, 'confirmed', AdminAuthService::currentId())['success']));
check('state machine: confirmed → checkin', !empty($abs->updateStatus($bk2Id, 'checkin', AdminAuthService::currentId())['success']));
check('state machine: checkin → checkout', !empty($abs->updateStatus($bk2Id, 'checkout', AdminAuthService::currentId())['success']));
check('state machine: checkout → confirmed bị chặn', ($abs->updateStatus($bk2Id, 'confirmed', AdminAuthService::currentId())['status'] ?? 0) === 422);
check('state machine: pending → checkin bị chặn (skip)', ($abs->updateStatus($bk2Id, 'checkin', AdminAuthService::currentId())['status'] ?? 0) === 422);

// payment status
check('payment: unpaid → paid', !empty($abs->updatePaymentStatus($bk2Id, 'paid', AdminAuthService::currentId())['success']));
check('payment: status không hợp lệ → 422', ($abs->updatePaymentStatus($bk2Id, 'hacked', AdminAuthService::currentId())['status'] ?? 0) === 422);

// audit log ghi nhận
$audit = $db->select('audit_logs', ['select'=>'action,entity_type,entity_id', 'filters'=>Pg::eqInt('entity_id', $bk2Id), 'limit'=>10]);
check('audit_logs có CHANGE_BOOKING_STATUS', in_array('CHANGE_BOOKING_STATUS', array_column($audit['data'] ?? [], 'action'), true));
check('audit_logs có CHANGE_PAYMENT_STATUS', in_array('CHANGE_PAYMENT_STATUS', array_column($audit['data'] ?? [], 'action'), true));
}

/* ═════════════════════════════════════════════════════════════════════════
   9. FAVORITES + REVIEW (P1)
   ═════════════════════════════════════════════════════════════════════════ */
section('9. Favorites & Review');
$fav = new FavoriteService();
$favAdd = $fav->add($customerId, 13);
check('add favorite success', !empty($favAdd['success']));
$favAddDup = $fav->add($customerId, 13);
check('add favorite trùng → vẫn ok (idempotent)', !empty($favAddDup['success']));
$favList = $fav->list($customerId);
check('favorites list có unit 13', count($favList['data'] ?? []) === 1);
check('favorite có unit enrich', !empty($favList['data'][0]['unit']['name']));
$favDel = $fav->remove($customerId, 13);
check('remove favorite success', !empty($favDel['success']));

$review = new ReviewService();
if (!$rpcAvailable) {
    echo "  ⚠️ SKIP — review cần booking checkout (chạy 006_booking_rpc.sql trước)\n";
} else {
$revBad = $review->create($customerId, ['booking_id' => $bk2Id, 'overall_rating' => 5, 'cleanliness_rating' => 5, 'location_rating' => 5, 'amenities_rating' => 5, 'accuracy_rating' => 5, 'communication_rating' => 5, 'value_rating' => 5, 'title' => 'Live test', 'comment' => 'Rất tốt']);
check('review khi booking checkout → success', !empty($revBad['success']), $revBad['message'] ?? '');
check('review status = pending', ($revBad['data']['status'] ?? '') === 'pending');
$revDup = $review->create($customerId, ['booking_id' => $bk2Id, 'overall_rating' => 4, 'cleanliness_rating' => 4, 'location_rating' => 4, 'amenities_rating' => 4, 'accuracy_rating' => 4, 'communication_rating' => 4, 'value_rating' => 4, 'comment' => 'lần 2']);
check('review trùng booking → 409', ($revDup['status'] ?? 0) === 409);

$revMod = $review->moderate((int)($revBad['data']['id'] ?? 0), 'approved', AdminAuthService::currentId());
check('admin approve review', !empty($revMod['success']));
}

/* ═════════════════════════════════════════════════════════════════════════
   10. YANGMATCH + CONTACT
   ═════════════════════════════════════════════════════════════════════════ */
section('10. YangMatch & Contact');
$ym = (new YangMatchService())->match(['q1' => 'couple', 'q2' => 'romantic', 'q3' => ['balcony'], 'q4' => 1500000], 6);
check('yangmatch success', !empty($ym['success']));
check('yangmatch có matches', count($ym['data']['matches'] ?? []) > 0);
check('yangmatch deterministic (chạy lại giống)', json_encode($ym['data']['matches']) === json_encode((new YangMatchService())->match(['q1' => 'couple', 'q2' => 'romantic', 'q3' => ['balcony'], 'q4' => 1500000], 6)['data']['matches']));
check('yangmatch score là số', is_numeric($ym['data']['matches'][0]['score_pct'] ?? null));

$ct = (new ContactService())->submit(['full_name' => 'Live Tester', 'email' => $testEmail, 'phone' => '', 'subject' => 'Live test', 'message' => 'Test message from live suite']);
check('contact submit success', !empty($ct['success']));

/* ═════════════════════════════════════════════════════════════════════════
   11. CLEANUP — xóa dữ liệu TEST (không phá seed)
   ═════════════════════════════════════════════════════════════════════════ */
section('11. Cleanup test data');
$db->delete('reviews', Pg::eqInt('customer_id', $customerId));          // FK reviews.booking RESTRICT → xóa review trước
$db->delete('bookings', Pg::eqInt('customer_id', $customerId));         // cascade nights/items/usages
$db->delete('contact_messages', Pg::eqStr('email', $testEmail));
$db->delete('customers', Pg::eqInt('id', $customerId));                 // cascade favorites

$gone = $db->select('customers', ['select'=>'id', 'filters'=>Pg::eqInt('id', $customerId), 'limit'=>1]);
check('test customer đã xóa', count($gone['data'] ?? []) === 0);
$propsFinal = $db->select('properties', ['select'=>'id', 'limit'=>20]);
check('seed properties vẫn = 5', count($propsFinal['data'] ?? []) === 5, 'count=' . count($propsFinal['data'] ?? []));

/* ── Kết quả ── */
echo "\n════════════════════════════════════════════\n";
echo "KẾT QUẢ LIVE TEST: $PASS pass / $FAIL fail\n";
if ($FAILED_NAMES) {
    echo "FAILED:\n";
    foreach ($FAILED_NAMES as $n) echo "  - $n\n";
}
echo "════════════════════════════════════════════\n";
exit($FAIL === 0 ? 0 : 1);
