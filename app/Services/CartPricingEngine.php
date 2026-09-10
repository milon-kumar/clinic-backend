<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;

class CartPricingEngine
{
    public function __construct(
        private ClinicCatalogService $catalogService,
        private PromotionService $promotionService,
        private PrerequisiteService $prerequisiteService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function priceCart(Cart $cart, ?User $customer = null): array
    {
        $cart->load(['lines.service', 'clinic']);

        $lines = [];
        $subtotalPence = 0;
        $tierDiscountPence = 0;
        $allAvailable = true;
        $checkoutBlockReason = null;

        foreach ($cart->lines as $line) {
            $mode = $cart->cart_type === 'book' ? 'book' : 'buy';
            $availability = $this->catalogService->isAvailable(
                $cart->clinic_id ?? 0,
                $line->service_id,
                $mode
            );

            $price = $cart->clinic_id
                ? $this->catalogService->resolvePrice($cart->clinic_id, $line->service_id, $line->quantity)
                : [
                    'unitPricePence' => $line->unit_price_pence,
                    'listSubtotalPence' => $line->unit_price_pence * $line->quantity,
                    'subtotalPence' => $line->unit_price_pence * $line->quantity,
                    'discountPence' => 0,
                    'tierDiscountPercent' => 0,
                ];

            $lineSubtotal = $price['listSubtotalPence'] ?? ($price['unitPricePence'] * $line->quantity);
            $lineTierDiscount = $price['discountPence'] ?? (int) round($lineSubtotal * (($price['tierDiscountPercent'] ?? 0) / 100));
            $lineTotal = $price['subtotalPence'] ?? ($lineSubtotal - $lineTierDiscount);

            $prereq = $customer && $cart->clinic_id
                ? $this->prerequisiteService->check($customer->id, $line->service_id, $cart->clinic_id)
                : ['satisfied' => true, 'missing' => []];

            if (! $availability['ok']) {
                $allAvailable = false;
                $checkoutBlockReason = 'CLINIC_AVAILABILITY';
            }

            if (! $prereq['satisfied']) {
                $allAvailable = false;
                $checkoutBlockReason = 'PREREQUISITES_NOT_MET';
            }

            $lines[] = [
                'lineId' => $line->id,
                'serviceId' => $line->service_id,
                'name' => $line->service->name,
                'quantity' => $line->quantity,
                'unitPricePence' => $price['unitPricePence'],
                'listTotalPence' => $lineSubtotal,
                'discountPercent' => $price['tierDiscountPercent'],
                'discountPence' => $lineTierDiscount,
                'lineTotalPence' => $lineTotal,
                'available' => $availability['ok'] && $prereq['satisfied'],
                'unavailableReason' => $availability['reason'] ?? (!$prereq['satisfied'] ? 'PREREQUISITES_NOT_MET' : null),
                'missingPrerequisites' => $prereq['missing'],
            ];

            $subtotalPence += $lineSubtotal;
            $tierDiscountPence += $lineTierDiscount;
        }

        $afterTier = $subtotalPence - $tierDiscountPence;

        $autoPromo = $this->promotionService->applyAutoPromotions($cart, $afterTier);
        $afterAuto = $afterTier - $autoPromo['discountPence'];

        $promoDiscount = 0;
        if ($cart->promo_code) {
            $promoResult = $this->promotionService->applyPromoCode(
                $cart->promo_code,
                $cart,
                $afterAuto,
                $customer
            );
            $promoDiscount = $promoResult['discountPence'];
        }

        $totalPence = max(0, $afterAuto - $promoDiscount);
        $totalDiscount = $tierDiscountPence + $autoPromo['discountPence'] + $promoDiscount;

        if (! $cart->clinic_id) {
            $allAvailable = false;
            $checkoutBlockReason = 'NO_CLINIC_SELECTED';
        }

        if ($cart->lines->isEmpty()) {
            $allAvailable = false;
            $checkoutBlockReason = 'EMPTY_CART';
        }

        return [
            'cartId' => $cart->id,
            'clinicId' => $cart->clinic_id,
            'clinicName' => $cart->clinic?->name,
            'type' => $cart->cart_type,
            'promoCode' => $cart->promo_code,
            'lines' => $lines,
            'summary' => [
                'subtotalPence' => $subtotalPence,
                'tierDiscountPence' => $tierDiscountPence,
                'autoDiscountPence' => $autoPromo['discountPence'],
                'promoDiscountPence' => $promoDiscount,
                'discountPence' => $totalDiscount,
                'totalPence' => $totalPence,
                'canCheckout' => $allAvailable && $cart->lines->isNotEmpty() && $cart->clinic_id !== null,
                'checkoutBlockReason' => $allAvailable ? null : $checkoutBlockReason,
            ],
        ];
    }
}
