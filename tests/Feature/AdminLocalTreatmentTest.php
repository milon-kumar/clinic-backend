<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\PlatformTestCase;

class AdminLocalTreatmentTest extends PlatformTestCase
{
    public function test_local_treatment_creates_customer_and_imports_package(): void
    {
        $clinic = $this->createClinic(['slug' => 'local-tx', 'code' => 'LOCAL_TX']);
        $service = $this->createService([
            'name' => 'Local Laser',
            'allow_local' => true,
            'base_price_pence' => 10000,
        ]);
        $this->attachServiceToClinic($clinic, $service);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/treatment-journeys', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'firstName' => 'Amina',
            'lastName' => 'Khan',
            'email' => 'amina.local@example.com',
            'phone' => '07000000001',
            'sessions' => 3,
            'price' => 240,
            'bookingDate' => now()->toDateString(),
            'bookingTime' => '11:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.serviceName', 'Local Laser')
            ->assertJsonPath('data.patientName', 'Amina Khan')
            ->assertJsonPath('data.sessionsTotal', 3)
            ->assertJsonPath('data.sessionsUsed', 0)
            ->assertJsonPath('data.sessionsRemaining', 3)
            ->assertJsonPath('data.pricePence', 24000);

        $this->assertDatabaseHas('users', [
            'email' => 'amina.local@example.com',
            'role' => 'patient',
            'first_name' => 'Amina',
        ]);
        $this->assertDatabaseHas('prepaid_packages', [
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 3,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('appointments', [
            'email' => 'amina.local@example.com',
            'status' => 'confirmed',
        ]);
    }

    public function test_treatment_without_local_flag_cannot_be_imported(): void
    {
        $clinic = $this->createClinic(['slug' => 'no-local', 'code' => 'NO_LOCAL']);
        $service = $this->createService(['allow_local' => false]);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/treatment-journeys', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'firstName' => 'No',
            'lastName' => 'Local',
            'email' => 'no.local@example.com',
            'sessions' => 1,
        ])->assertStatus(422);
    }

    public function test_existing_customer_can_receive_local_treatment(): void
    {
        $clinic = $this->createClinic(['slug' => 'exist-local', 'code' => 'EX_LOCAL']);
        $service = $this->createService(['name' => 'Hydrafacial', 'allow_local' => true]);
        $customer = $this->createUser([
            'email' => 'existing.local@example.com',
            'first_name' => 'Existing',
            'last_name' => 'Client',
        ]);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/treatment-journeys', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'customerId' => $customer->id,
            'sessions' => 1,
            'price' => 80,
        ])
            ->assertCreated()
            ->assertJsonPath('data.patientEmail', 'existing.local@example.com');

        $this->assertSame(1, User::query()->where('email', 'existing.local@example.com')->count());
        $this->assertDatabaseHas('prepaid_packages', [
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);
    }
}
