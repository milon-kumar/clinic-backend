<?php

namespace Tests\Unit\Services;

use App\Models\Cart;
use App\Models\CartLine;
use App\Services\CartPricingEngine;
use Tests\PlatformTestCase;

class CartPricingEngineTest extends PlatformTestCase
{
    public function test_price_cart_blocks_when_no_clinic_selected(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        $cart = Cart::create([
            'customer_id' => null,
            'clinic_id' => null,
            'cart_type' => 'buy',
        ]);

        CartLine::create([
            'cart_id' => $cart->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_price_pence' => 10000,
        ]);

        $result = app(CartPricingEngine::class)->priceCart($cart);

        $this->assertFalse($result['summary']['canCheckout']);
        $this->assertSame('NO_CLINIC_SELECTED', $result['summary']['checkoutBlockReason']);
    }

    public function test_price_cart_applies_tier_discount_for_six_sessions(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['base_price_pence' => 10000]);
        $this->attachServiceToClinic($clinic, $service);

        $cart = Cart::create([
            'clinic_id' => $clinic->id,
            'cart_type' => 'buy',
        ]);

        CartLine::create([
            'cart_id' => $cart->id,
            'service_id' => $service->id,
            'quantity' => 6,
            'unit_price_pence' => 10000,
        ]);

        $result = app(CartPricingEngine::class)->priceCart($cart);

        $this->assertTrue($result['summary']['canCheckout']);
        $this->assertSame(60000, $result['summary']['subtotalPence']);
        $this->assertSame(18000, $result['summary']['tierDiscountPence']);
        $this->assertSame(42000, $result['summary']['totalPence']);
    }

    public function test_line_total_scales_when_client_changes_quantity(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['base_price_pence' => 10000]);
        $this->attachServiceToClinic($clinic, $service);

        $cart = Cart::create([
            'clinic_id' => $clinic->id,
            'cart_type' => 'buy',
        ]);

        $line = CartLine::create([
            'cart_id' => $cart->id,
            'service_id' => $service->id,
            'quantity' => 2,
            'unit_price_pence' => 10000,
        ]);

        $engine = app(CartPricingEngine::class);

        $two = $engine->priceCart($cart->fresh(['lines']));
        $this->assertSame(2, $two['lines'][0]['quantity']);
        $this->assertSame(20000, $two['lines'][0]['lineTotalPence']);
        $this->assertSame(20000, $two['summary']['totalPence']);

        $line->update(['quantity' => 3]);

        $three = $engine->priceCart($cart->fresh(['lines']));
        $this->assertSame(3, $three['lines'][0]['quantity']);
        $this->assertSame(24000, $three['lines'][0]['lineTotalPence']);
        $this->assertSame(6000, $three['summary']['tierDiscountPence']);
        $this->assertSame(24000, $three['summary']['totalPence']);
    }
}
