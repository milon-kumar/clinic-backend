<?php

namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class AdminClinicApiTest extends PlatformTestCase
{
    public function test_staff_can_edit_clinic_matrix(): void
    {
        $admin = $this->createUser(['role' => 'admin', 'username' => 'matrixadmin']);
        $clinic = $this->createClinic();
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/clinics/{$clinic->id}/matrix")
            ->assertOk()
            ->assertJsonPath('data.services.0.buyEnabled', true);

        $this->putJson("/api/v1/admin/clinics/{$clinic->id}/matrix", [
            'services' => [[
                'serviceId' => $service->id,
                'offered' => true,
                'buyEnabled' => false,
                'bookEnabled' => true,
                'onlineBuyEnabled' => false,
                'pricePence' => 8500,
            ]],
        ])->assertOk()->assertJsonPath('data.services.0.buyEnabled', false);

        $this->assertDatabaseHas('clinic_services', [
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'buy_enabled' => 0,
            'price_pence' => 8500,
        ]);
    }

    public function test_patient_cannot_access_admin_clinics(): void
    {
        $patient = $this->createUser(['role' => 'patient']);
        Sanctum::actingAs($patient);

        $this->getJson('/api/v1/admin/clinics')->assertForbidden();
        $this->getJson('/api/v1/admin/reports')->assertForbidden();
    }

    public function test_admin_reports_endpoint(): void
    {
        $admin = $this->createUser(['role' => 'admin', 'username' => 'reporter']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonStructure(['data' => ['appointments', 'users', 'services', 'orders', 'clinics']]);
    }
}
