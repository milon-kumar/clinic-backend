<?php

namespace Tests\Unit\Services;

use App\Services\ClinicCatalogService;
use Tests\TestCase;

class ClinicCatalogServicePureTest extends TestCase
{
    public function test_tier_discount_percent_without_database(): void
    {
        $service = new ClinicCatalogService;

        $this->assertSame(0, $service->tierDiscountPercent(1));
        $this->assertSame(20, $service->tierDiscountPercent(3));
        $this->assertSame(30, $service->tierDiscountPercent(6));
        $this->assertSame(40, $service->tierDiscountPercent(10));
    }

    public function test_quantity_below_three_has_no_volume_discount(): void
    {
        $service = new ClinicCatalogService;

        $this->assertSame(0, $service->tierDiscountPercent(2));
    }
}
