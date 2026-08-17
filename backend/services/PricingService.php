<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * PRICING SERVICE — tính giá TỪNG ĐÊM (snapshot)
 *
 * • Không có rate rule → nightly_rate = units.base_price
 * • Rate rule thắng theo: priority DESC → cụ thể hơn (có day_of_week;
 *   có start+end) → updated_at DESC → id DESC  (KHỚP create_booking_atomic)
 * • day_of_week: 1=CN ... 7=T7 (quy ước lưu trữ; PHP: (int)date('w') + 1)
 * • start_date inclusive / end_date EXCLUSIVE
 * • Booking sau khi tạo luôn lưu snapshot (booking_nights) — admin đổi giá
 *   không ảnh hưởng booking cũ.
 *
 * Phần PURE (static) tách riêng để unit-test không cần Supabase.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class PricingService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    /**
     * Endpoint booking-quote: unit + dates + guests + promotion_code.
     */
    public function quote(int $unitId, string $checkIn, string $checkOut, ?string $promotionCode = null, ?int $guests = null): array
    {
        $dateErr = AvailabilityService::validateDates($checkIn, $checkOut);
        if ($dateErr !== null) {
            return ['success' => false, 'message' => $dateErr, 'status' => 422];
        }

        $res = $this->db->select('units', [
            'select'  => 'id,name,base_price,cleaning_fee,max_guests,status',
            'filters' => [Pg::eqInt('id', $unitId), Pg::eqStr('status', 'active')],
            'limit'   => 1,
        ]);
        $unit = ($res['ok'] && is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
        if ($unit === null) {
            return ['success' => false, 'message' => 'Không tìm thấy căn homestay', 'status' => 404];
        }

        if ($guests !== null && $guests > (int)$unit['max_guests']) {
            return ['success' => false, 'message' => 'Số khách vượt sức chứa của căn này.', 'status' => 422];
        }

        $rules = $this->rateRulesForUnit($unitId);

        // Promotion: tính subtotal trước rồi validate (min booking value dựa trên subtotal).
        $promotion = null;
        if ($promotionCode !== null && trim($promotionCode) !== '') {
            $quoteBase = self::computeQuote($unit, $rules, $checkIn, $checkOut, null, $guests);
            try {
                [$discount, $promo] = (new PromotionService())->validate(
                    trim($promotionCode),
                    (int)$quoteBase['subtotal'],
                    0 // quote không gắn customer cụ thể — usage-per-customer bỏ qua ở quote
                );
                $promotion = $promo;
            } catch (ValidationException $e) {
                return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
            }
        }

        $quote = self::computeQuote($unit, $rules, $checkIn, $checkOut, $promotion, $guests);

        return ['success' => true, 'data' => $quote, 'status' => 200];
    }

    private function rateRulesForUnit(int $unitId): array
    {
        $res = $this->db->select('rate_rules', [
            'select'  => 'id,unit_id,name,start_date,end_date,day_of_week,price,priority,active,updated_at',
            'filters' => [Pg::eqInt('unit_id', $unitId), Pg::eqStr('active', 'true')],
            'limit'   => 100,
        ]);
        return $res['ok'] ? $res['data'] : [];
    }

    /* ────────────────────────────────────────────────────────────────
     * PURE CORE (testable, không cần Supabase)
     * ──────────────────────────────────────────────────────────────── */

    /**
     * Chọn rate rule cho 1 đêm (hoặc null → base_price).
     */
    public static function pickRule(array $rules, string $dateYmd): ?array
    {
        $dow = (int)date('w', strtotime($dateYmd)) + 1; // 1=CN..7=T7

        $candidates = array_values(array_filter($rules, function ($r) use ($dow, $dateYmd) {
            if (empty($r['active'])) {
                return false;
            }
            if (($r['day_of_week'] ?? null) !== null && (int)$r['day_of_week'] !== $dow) {
                return false;
            }
            if (($r['start_date'] ?? null) !== null && $dateYmd < $r['start_date']) {
                return false;
            }
            if (($r['end_date'] ?? null) !== null && $dateYmd >= $r['end_date']) { // end EXCLUSIVE
                return false;
            }
            return true;
        }));

        usort($candidates, static function ($a, $b) {
            $pa = (int)($a['priority'] ?? 0);
            $pb = (int)($b['priority'] ?? 0);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            $sa = self::specificity($a);
            $sb = self::specificity($b);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $ta = (string)($a['updated_at'] ?? '');
            $tb = (string)($b['updated_at'] ?? '');
            if ($ta !== $tb) {
                return strcmp($tb, $ta);
            }
            return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
        });

        return $candidates[0] ?? null;
    }

    /** Mức "cụ thể": có day_of_week (+1), có start+end (+2), chỉ 1 trong 2 (+1). */
    private static function specificity(array $r): int
    {
        $s = 0;
        if (($r['day_of_week'] ?? null) !== null) {
            $s += 1;
        }
        $hasStart = ($r['start_date'] ?? null) !== null;
        $hasEnd   = ($r['end_date'] ?? null) !== null;
        if ($hasStart && $hasEnd) {
            $s += 2;
        } elseif ($hasStart || $hasEnd) {
            $s += 1;
        }
        return $s;
    }

    /**
     * Tính quote đầy đủ (testable).
     *
     * @param array  $unit   ['base_price'=>int,'cleaning_fee'=>int,'max_guests'=>int,...]
     * @param array  $rules  rate_rules list
     * @param string $checkIn  Y-m-d
     * @param string $checkOut Y-m-d
     * @param array|null $promotion (đã validate) hoặc null
     */
    public static function computeQuote(array $unit, array $rules, string $checkIn, string $checkOut, ?array $promotion = null, ?int $guests = null): array
    {
        $nights = [];
        $subtotal = 0;
        $d = new DateTimeImmutable($checkIn);
        $end = new DateTimeImmutable($checkOut);

        while ($d < $end) {
            $ymd = $d->format('Y-m-d');
            $rule = self::pickRule($rules, $ymd);
            $rate = $rule !== null ? (int)$rule['price'] : (int)$unit['base_price'];
            $nights[] = [
                'date'         => $ymd,
                'rate'         => $rate,
                'rate_rule_id' => $rule !== null ? (int)$rule['id'] : null,
                'rule_name'    => $rule['name'] ?? null,
            ];
            $subtotal += $rate;
            $d = $d->modify('+1 day');
        }

        $cleaning = (int)($unit['cleaning_fee'] ?? 0);
        $discount = 0;
        $promoCode = null;
        if ($promotion !== null) {
            $discount = PromotionService::computeDiscount($promotion, $subtotal);
            $promoCode = (string)($promotion['code'] ?? '');
        }
        $total = max(0, $subtotal + $cleaning - $discount);

        return [
            'unit_id'          => (int)($unit['id'] ?? 0),
            'unit_name'        => (string)($unit['name'] ?? ''),
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'number_of_nights' => count($nights),
            'guests'           => $guests,
            'nights'           => $nights,
            'subtotal'         => $subtotal,
            'cleaning_fee'     => $cleaning,
            'discount_amount'  => $discount,
            'total_amount'     => $total,
            'currency'         => 'VND',
            'promotion_code'   => $promoCode,
        ];
    }

    /** Format VND: 850.000 ₫ */
    public static function formatVnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }
}
