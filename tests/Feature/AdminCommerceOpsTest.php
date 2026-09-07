<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Supplier;
use Tests\PlatformTestCase;

class AdminCommerceOpsTest extends PlatformTestCase
{
    public function test_superadmin_can_restock_and_see_inventory(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/inventory/restock', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'quantity' => 20,
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantityOnHand', 20);

        $this->getJson('/api/v1/admin/inventory?clinicId='.$clinic->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_paid_order_can_issue_an_invoice(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();
        $customer = $this->createUser();
        $order = Order::create([
            'customer_id' => $customer->id,
            'clinic_id' => $clinic->id,
            'status' => 'paid',
            'subtotal_pence' => 10000,
            'total_pence' => 10000,
            'paid_at' => now(),
        ]);
        OrderLine::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_price_pence' => 10000,
        ]);

        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/invoices', ['orderId' => $order->id])
            ->assertCreated()
            ->assertJsonPath('data.totalPence', 10000);

        $this->getJson('/api/v1/admin/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_revenue_sheet_includes_paid_orders(): void
    {
        $clinic = $this->createClinic();
        $customer = $this->createUser();
        Order::create([
            'customer_id' => $customer->id,
            'clinic_id' => $clinic->id,
            'status' => 'paid',
            'total_pence' => 15000,
            'paid_at' => now(),
        ]);

        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.orders.revenuePence', 15000)
            ->assertJsonCount(1, 'data.sheet');
    }

    public function test_superadmin_can_add_a_supplier(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/suppliers', [
            'name' => 'Laser Consumables Ltd',
            'category' => 'consumables',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Laser Consumables Ltd');

        $this->assertDatabaseHas('suppliers', ['name' => 'Laser Consumables Ltd']);
        $this->assertInstanceOf(Supplier::class, Supplier::first());
    }
}
