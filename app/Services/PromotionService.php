<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Promotion;
use App\Models\User;

class PromotionService
{
    /**
     * @return array{discountPence: int, promotion: ?Promotion}
     */
    public function applyPromoCode(string $code, Cart $cart, int $subtotalPence, ?User $customer = null): array
    {
        $promotion = Promotion::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $promotion || ! $promotion->isValidForClinic($cart->clinic_id)) {
            return ['discountPence' => 0, 'promotion' => null, 'error' => 'INVALID_PROMO_CODE'];
        }

        if ($promotion->requires_login && ! $customer) {
            return ['discountPence' => 0, 'promotion' => null, 'error' => 'LOGIN_REQUIRED'];
        }

        $discount = $this->calculateDiscount($promotion, $subtotalPence, $cart);

        return ['discountPence' => $discount, 'promotion' => $promotion, 'error' => null];
    }

    /**
     * @return array{discountPence: int, promotions: array<int, Promotion>}
     */
    public function applyAutoPromotions(Cart $cart, int $subtotalPence): array
    {
        $promotions = Promotion::query()
            ->where('type', 'auto')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Promotion $p) => $p->isValidForClinic($cart->clinic_id));

        $totalDiscount = 0;
        $applied = [];

        foreach ($promotions as $promotion) {
            $discount = $this->calculateDiscount($promotion, $subtotalPence, $cart);
            if ($discount > 0) {
                $totalDiscount += $discount;
                $applied[] = $promotion;
            }
        }

        return ['discountPence' => $totalDiscount, 'promotions' => $applied];
    }

    private function calculateDiscount(Promotion $promotion, int $subtotalPence, Cart $cart): int
    {
        $rules = $promotion->rules_json ?? [];

        return match ($promotion->type) {
            'percent' => (int) round($subtotalPence * (($rules['percent'] ?? 0) / 100)),
            'fixed' => min($subtotalPence, (int) ($rules['amount_pence'] ?? 0)),
            'bogo' => $this->calculateBogoDiscount($cart, $rules),
            'tier_volume' => $this->calculateTierVolumeDiscount($cart, $rules),
            'auto' => (int) round($subtotalPence * (($rules['percent'] ?? 0) / 100)),
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function calculateBogoDiscount(Cart $cart, array $rules): int
    {
        $cart->loadMissing('lines');
        $discount = 0;

        foreach ($cart->lines as $line) {
            if ($line->quantity >= 2) {
                $freeItems = (int) floor($line->quantity / 2);
                $discount += $freeItems * $line->unit_price_pence;
            }
        }

        return $discount;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function calculateTierVolumeDiscount(Cart $cart, array $rules): int
    {
        $cart->loadMissing('lines');
        $totalQty = $cart->lines->sum('quantity');
        $tiers = $rules['tiers'] ?? [];

        $percent = 0;
        foreach ($tiers as $tier) {
            if ($totalQty >= ($tier['min_qty'] ?? 0)) {
                $percent = max($percent, (int) ($tier['percent'] ?? 0));
            }
        }

        return (int) round($cart->lines->sum(fn ($l) => $l->unit_price_pence * $l->quantity) * ($percent / 100));
    }
}
