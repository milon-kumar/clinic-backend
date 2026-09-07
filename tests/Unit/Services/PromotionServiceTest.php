<?php

namespace Tests\Unit\Services;

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\Promotion;
use App\Services\PromotionService;
use Tests\PlatformTestCase;

class PromotionServiceTest extends PlatformTestCase
{
    public function test_apply_promo_code_percent_discount(): void
    {
        $clinic = $this->createClinic();
        Promotion::create([
            'code' => 'SAVE10',
            'type' => 'percent',
            'rules_json' => ['percent' => 10],
            'requires_login' => false,
            'is_active' => true,
        ]);

        $cart = Cart::create(['clinic_id' => $clinic->id, 'cart_type' => 'buy', 'promo_code' => 'SAVE10']);

        $result = app(PromotionService::class)->applyPromoCode('SAVE10', $cart, 10000);

        $this->assertSame(1000, $result['discountPence']);
    }

    public function test_apply_promo_code_rejects_invalid_code(): void
    {
        $clinic = $this->createClinic();
        $cart = Cart::create(['clinic_id' => $clinic->id, 'cart_type' => 'buy']);

        $result = app(PromotionService::class)->applyPromoCode('INVALID', $cart, 10000);

        $this->assertSame(0, $result['discountPence']);
        $this->assertSame('INVALID_PROMO_CODE', $result['error']);
    }

    public function test_apply_auto_promotions(): void
    {
        $clinic = $this->createClinic();
        Promotion::create([
            'type' => 'auto',
            'rules_json' => ['percent' => 5],
            'is_active' => true,
        ]);

        $cart = Cart::create(['clinic_id' => $clinic->id, 'cart_type' => 'buy']);

        $result = app(PromotionService::class)->applyAutoPromotions($cart, 20000);

        $this->assertSame(1000, $result['discountPence']);
    }

    public function test_bogo_discount_on_cart_lines(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();

        $cart = Cart::create(['clinic_id' => $clinic->id, 'cart_type' => 'buy']);
        CartLine::create([
            'cart_id' => $cart->id,
            'service_id' => $service->id,
            'quantity' => 4,
            'unit_price_pence' => 5000,
        ]);

        $promo = Promotion::create([
            'code' => 'BOGO',
            'type' => 'bogo',
            'rules_json' => [],
            'is_active' => true,
        ]);

        $discount = (new \ReflectionClass(PromotionService::class))
            ->getMethod('calculateDiscount')
            ->invoke(app(PromotionService::class), $promo, 20000, $cart);

        $this->assertSame(10000, $discount);
    }
}
