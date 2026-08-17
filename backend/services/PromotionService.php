<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * PROMOTION SERVICE — validate + tính discount
 *
 * RPC create_booking_atomic() re-validate atomic khi đặt (nguồn sự thật cuối).
 * Service này dùng cho booking-quote + kiểm tra trước.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class PromotionService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    /**
     * Lấy promotion theo code (không check validity — chỉ get record).
     */
    public function findByCode(string $code): ?array
    {
        $res = $this->db->select('promotions', [
            'select'  => 'id,code,name,description,discount_type,discount_value,minimum_booking_value,maximum_discount,valid_from,valid_until,usage_limit,usage_limit_per_customer,active',
            'filters' => Pg::eqStr('code', $code),
            'limit'   => 1,
        ]);
        return ($res['ok'] && is_array($res['data']) && isset($res['data'][0])) ? $res['data'][0] : null;
    }

    /**
     * Validate + trả [discount_amount, promotion] hoặc ném ValidationException
     * với message thân thiện.
     *
     * @throws ValidationException
     */
    public function validate(string $code, int $subtotal, int $customerId): array
    {
        $promo = $this->findByCode($code);
        if ($promo === null) {
            throw new ValidationException('Mã ưu đãi không hợp lệ.', ['promotion_code' => 'Mã ưu đãi không hợp lệ.']);
        }
        if (empty($promo['active'])) {
            throw new ValidationException('Mã ưu đãi không hợp lệ.', ['promotion_code' => 'Mã ưu đãi không hợp lệ.']);
        }
        $today = date('Y-m-d');
        if (($promo['valid_from'] ?? '') > $today || ($promo['valid_until'] ?? '') < $today) {
            throw new ValidationException('Mã ưu đãi đã hết hạn.', ['promotion_code' => 'Mã ưu đãi đã hết hạn.']);
        }
        if (($promo['minimum_booking_value'] ?? null) !== null && $subtotal < (int)$promo['minimum_booking_value']) {
            throw new ValidationException('Đơn chưa đạt giá trị tối thiểu để dùng mã ưu đãi.', ['promotion_code' => 'Đơn chưa đạt giá trị tối thiểu.']);
        }

        // usage limits
        if (($promo['usage_limit'] ?? null) !== null) {
            $used = $this->usageCount((int)$promo['id']);
            if ($used >= (int)$promo['usage_limit']) {
                throw new ValidationException('Mã ưu đãi đã hết lượt sử dụng.', ['promotion_code' => 'Mã ưu đãi đã hết lượt sử dụng.']);
            }
        }
        if (($promo['usage_limit_per_customer'] ?? null) !== null) {
            $usedByCustomer = $this->usageCountByCustomer((int)$promo['id'], $customerId);
            if ($usedByCustomer >= (int)$promo['usage_limit_per_customer']) {
                throw new ValidationException('Bạn đã sử dụng mã ưu đãi này rồi.', ['promotion_code' => 'Bạn đã sử dụng mã này rồi.']);
            }
        }

        $discount = self::computeDiscount($promo, $subtotal);
        return [$discount, $promo];
    }

    /**
     * Tính discount thuần (testable).
     * Percentage: subtotal × % (cap maximum_discount). Fixed: discount_value (cap max).
     */
    public static function computeDiscount(array $promo, int $subtotal): int
    {
        $max = ($promo['maximum_discount'] ?? null) !== null ? (int)$promo['maximum_discount'] : null;

        if (($promo['discount_type'] ?? '') === 'percentage') {
            $discount = (int)round($subtotal * (int)$promo['discount_value'] / 100.0);
        } else {
            $discount = (int)$promo['discount_value'];
        }
        if ($max !== null && $discount > $max) {
            $discount = $max;
        }
        return max(0, $discount);
    }

    private function usageCount(int $promoId): int
    {
        $res = $this->db->select('promotion_usages', [
            'select'  => 'id',
            'filters' => Pg::eqInt('promotion_id', $promoId),
            'count'   => true,
        ]);
        return $res['ok'] && $res['count'] !== null ? $res['count'] : 0;
    }

    private function usageCountByCustomer(int $promoId, int $customerId): int
    {
        $res = $this->db->select('promotion_usages', [
            'select'  => 'id',
            'filters' => [Pg::eqInt('promotion_id', $promoId), Pg::eqInt('customer_id', $customerId)],
            'count'   => true,
        ]);
        return $res['ok'] && $res['count'] !== null ? $res['count'] : 0;
    }
}
