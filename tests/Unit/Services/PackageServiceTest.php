<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PrepaidPackage;
use App\Services\PackageService;
use Tests\PlatformTestCase;

class PackageServiceTest extends PlatformTestCase
{
    public function test_create_from_order_creates_prepaid_packages(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService();

        $order = Order::create([
            'customer_id' => $user->id,
            'clinic_id' => $clinic->id,
            'status' => 'paid',
            'subtotal_pence' => 30000,
            'discount_pence' => 0,
            'total_pence' => 30000,
            'paid_at' => now(),
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => 3,
            'unit_price_pence' => 10000,
        ]);

        $packages = app(PackageService::class)->createFromOrder($order);

        $this->assertCount(1, $packages);
        $this->assertSame(3, $packages->first()->sessions_total);
        $this->assertSame($clinic->id, $packages->first()->clinic_id);
    }

    public function test_redeem_decrements_sessions(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService();

        $package = PrepaidPackage::create([
            'customer_id' => $user->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 2,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        app(PackageService::class)->redeem($package->id, 1);

        $this->assertSame(1, $package->fresh()->sessions_used);
    }

    public function test_list_redeemable_filters_by_clinic(): void
    {
        $user = $this->createUser();
        $reading = $this->createClinic(['slug' => 'reading-pkg']);
        $london = $this->createClinic(['slug' => 'london-pkg', 'code' => 'LCUK_LON2']);
        $service = $this->createService();

        PrepaidPackage::create([
            'customer_id' => $user->id,
            'clinic_id' => $reading->id,
            'service_id' => $service->id,
            'sessions_total' => 3,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        PrepaidPackage::create([
            'customer_id' => $user->id,
            'clinic_id' => $london->id,
            'service_id' => $service->id,
            'sessions_total' => 3,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        $packages = app(PackageService::class)->listRedeemable($user->id, $reading->id);

        $this->assertCount(1, $packages);
        $this->assertSame($reading->id, $packages->first()->clinic_id);
    }
}
