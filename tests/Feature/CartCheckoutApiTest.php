<?php

namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class CartCheckoutApiTest extends PlatformTestCase
{
    public function test_clinic_switch_revalidates_cart_lines(): void
    {
        $user = $this->createUser();
        $reading = $this->createClinic(['slug' => 'reading-cart', 'code' => 'READ_CART']);
        $manchester = $this->createClinic(['slug' => 'man-cart', 'code' => 'MAN_CART']);
        $service = $this->createService();

        $this->attachServiceToClinic($reading, $service);
        $this->attachServiceToClinic($manchester, $service, [
            'buy_enabled' => false,
            'online_buy_enabled' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 1,
            'clinicId' => $reading->id,
            'type' => 'buy',
        ])
            ->assertOk()
            ->assertJsonPath('data.summary.canCheckout', true);

        $switched = $this->patchJson('/api/v1/cart/clinic', [
            'clinicId' => $manchester->id,
            'type' => 'buy',
        ]);

        $switched->assertOk();
        $this->assertFalse($switched->json('data.summary.canCheckout'));
    }

    public function test_buy_checkout_creates_clinic_locked_packages(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);
        $user->update(['selected_clinic_id' => $clinic->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 3,
            'clinicId' => $clinic->id,
        ])->assertOk();

        $confirm = $this->postJson('/api/v1/buy/checkout/confirm');

        $confirm->assertOk();
        $this->assertSame($clinic->id, $confirm->json('data.clinicId'));
        $this->assertCount(1, $confirm->json('data.packages'));
        $this->assertDatabaseHas('prepaid_packages', [
            'customer_id' => $user->id,
            'clinic_id' => $clinic->id,
            'sessions_total' => 3,
        ]);
    }

    public function test_add_line_requires_a_clinic(): void
    {
        $user = $this->createUser();
        $service = $this->createService();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 1,
            'type' => 'buy',
        ])->assertStatus(422);
    }

    public function test_customer_can_update_cart_line_quantity(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        Sanctum::actingAs($user);

        $added = $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 1,
            'clinicId' => $clinic->id,
        ])->assertOk();

        $lineId = $added->json('data.lines.0.lineId');

        $this->patchJson("/api/v1/cart/lines/{$lineId}", [
            'quantity' => 4,
            'type' => 'buy',
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 4);
    }

    public function test_cart_line_quantity_must_be_between_one_and_twenty(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        Sanctum::actingAs($user);

        $added = $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 1,
            'clinicId' => $clinic->id,
        ])->assertOk();

        $lineId = $added->json('data.lines.0.lineId');

        $this->patchJson("/api/v1/cart/lines/{$lineId}", [
            'quantity' => 0,
            'type' => 'buy',
        ])->assertStatus(422);

        $this->patchJson("/api/v1/cart/lines/{$lineId}", [
            'quantity' => 21,
            'type' => 'buy',
        ])->assertStatus(422);
    }

    public function test_superadmin_creates_treatment_at_every_branch(): void
    {
        $reading = $this->createClinic(['slug' => 'svc-reading', 'code' => 'SVC_R']);
        $london = $this->createClinic(['slug' => 'svc-london', 'code' => 'SVC_L']);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/services', [
            'name' => 'Laser Full Body',
            'category' => 'lhr',
            'basePricePence' => 29900,
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.clinicIds');

        $this->assertDatabaseHas('clinic_services', [
            'clinic_id' => $reading->id,
            'buy_enabled' => 1,
            'online_buy_enabled' => 1,
        ]);
        $this->assertDatabaseHas('clinic_services', [
            'clinic_id' => $london->id,
            'buy_enabled' => 1,
        ]);
    }

    public function test_receptionist_cannot_create_a_treatment(): void
    {
        $clinic = $this->createClinic();
        $this->actingAsUser($this->createUser([
            'role' => 'receptionist',
            'clinic_id' => $clinic->id,
        ]));

        $this->postJson('/api/v1/admin/services', [
            'name' => 'Branch only treatment',
        ])->assertForbidden();
    }

    public function test_cannot_checkout_empty_cart(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/buy/checkout/finalise')->assertStatus(422);
    }
}
