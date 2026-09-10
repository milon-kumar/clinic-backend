<?php

namespace Tests\Unit\Services;

use App\Services\ClinicCatalogService;
use Tests\PlatformTestCase;

class ClinicCatalogServiceTest extends PlatformTestCase
{
    private ClinicCatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClinicCatalogService::class);
    }

    public function test_tier_discount_percent(): void
    {
        $this->assertSame(0, $this->service->tierDiscountPercent(1));
        $this->assertSame(20, $this->service->tierDiscountPercent(3));
        $this->assertSame(30, $this->service->tierDiscountPercent(6));
        $this->assertSame(40, $this->service->tierDiscountPercent(10));
    }

    public function test_is_available_when_service_not_at_clinic(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();

        $result = $this->service->isAvailable($clinic->id, $service->id, 'buy');

        $this->assertFalse($result['ok']);
        $this->assertSame('NOT_OFFERED_AT_CLINIC', $result['reason']);
    }

    public function test_is_available_when_service_offered(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        $result = $this->service->isAvailable($clinic->id, $service->id, 'buy');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['buyEnabled']);
    }

    public function test_resolve_price_uses_clinic_override(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['base_price_pence' => 10000]);
        $this->attachServiceToClinic($clinic, $service, ['price_pence' => 8000]);

        $price = $this->service->resolvePrice($clinic->id, $service->id, 1);

        $this->assertSame(8000, $price['unitPricePence']);
    }

    public function test_resolve_price_scales_subtotal_with_selected_quantity(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['base_price_pence' => 5000]);
        $this->attachServiceToClinic($clinic, $service);

        $one = $this->service->resolvePrice($clinic->id, $service->id, 1);
        $this->assertSame(5000, $one['unitPricePence']);
        $this->assertSame(5000, $one['subtotalPence']);
        $this->assertSame(0, $one['tierDiscountPercent']);

        $three = $this->service->resolvePrice($clinic->id, $service->id, 3);
        $this->assertSame(5000, $three['unitPricePence']);
        $this->assertSame(20, $three['tierDiscountPercent']);
        $this->assertSame(12000, $three['subtotalPence']);
        $this->assertSame(15000, $three['listSubtotalPence']);
    }

    public function test_package_discount_overrides_quantity_tier(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['base_price_pence' => 18499]);
        $this->attachServiceToClinic($clinic, $service);
        $service->packages()->delete();
        $service->packages()->create([
            'title' => '3 Treatments',
            'sessions' => 3,
            'discount_percent' => 50,
            'price_pence' => 27749,
        ]);

        $quoted = $this->service->resolvePrice($clinic->id, $service->id, 3);

        $this->assertSame(50, $quoted['tierDiscountPercent']);
        $this->assertSame(18499 * 3, $quoted['listSubtotalPence']);
        $this->assertSame(27749, $quoted['subtotalPence']);
    }

    public function test_package_price_implies_discount_when_percent_unset(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['base_price_pence' => 18499]);
        $this->attachServiceToClinic($clinic, $service);
        $service->packages()->delete();
        $service->packages()->create([
            'title' => '3 Treatments',
            'sessions' => 3,
            'discount_percent' => 0,
            'price_pence' => 27749,
        ]);

        $quoted = $this->service->resolvePrice($clinic->id, $service->id, 3);

        $this->assertSame(50, $quoted['tierDiscountPercent']);
        $this->assertSame(27749, $quoted['subtotalPence']);
    }

    public function test_get_services_filters_buy_mode(): void
    {
        $clinic = $this->createClinic();
        $buyable = $this->createService(['slug' => 'buyable']);
        $bookOnly = $this->createService(['slug' => 'book-only', 'supports_buy' => false]);

        $this->attachServiceToClinic($clinic, $buyable);
        $this->attachServiceToClinic($clinic, $bookOnly, ['buy_enabled' => false]);

        $services = $this->service->getServices($clinic->id, 'buy');

        $this->assertCount(1, $services);
        $this->assertSame('buyable', $services->first()['slug']);
    }
}
