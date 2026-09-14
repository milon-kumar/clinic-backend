<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\PrepaidPackage;
use Tests\PlatformTestCase;

class AdminTreatmentSessionTest extends PlatformTestCase
{
    public function test_staff_can_start_and_complete_unbooked_package_session(): void
    {
        $admin = $this->createUser(['role' => 'superadmin']);
        $patient = $this->createUser(['first_name' => 'Mehedi', 'last_name' => 'Hasan', 'name' => 'Mehedi Hasan']);
        $clinic = $this->createClinic(['slug' => 'walk-in-tx', 'code' => 'WALK_TX']);
        $service = $this->createService(['name' => 'Hollywood Carbon Peel']);
        $this->attachServiceToClinic($clinic, $service);

        $package = PrepaidPackage::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 1,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        $this->actingAsUser($admin);
        $this->postJson('/api/v1/admin/treatment-journeys/'.$package->id.'/sessions', [
            'appointmentDate' => now()->toDateString(),
            'appointmentTime' => '14:30',
            'sessionNotes' => 'Skin calm. Use SPF.',
            'complete' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.sessionsUsed', 1)
            ->assertJsonPath('data.sessionsRemaining', 0)
            ->assertJsonPath('data.sessions.0.sessionNotes', 'Skin calm. Use SPF.')
            ->assertJsonPath('data.sessions.0.status', 'completed');

        $this->assertDatabaseHas('appointments', [
            'package_id' => $package->id,
            'status' => 'completed',
            'session_notes' => 'Skin calm. Use SPF.',
        ]);
        $this->assertDatabaseHas('prepaid_packages', [
            'id' => $package->id,
            'sessions_used' => 1,
            'status' => 'exhausted',
        ]);
    }

    public function test_staff_can_start_session_without_completing(): void
    {
        $admin = $this->createUser(['role' => 'superadmin']);
        $patient = $this->createUser();
        $clinic = $this->createClinic(['slug' => 'start-only', 'code' => 'START_ONLY']);
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);
        $package = PrepaidPackage::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 1,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        $this->actingAsUser($admin);
        $this->postJson('/api/v1/admin/treatment-journeys/'.$package->id.'/sessions', [
            'complete' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.sessionsUsed', 0)
            ->assertJsonPath('data.sessions.0.status', 'confirmed');

        $appointmentId = Appointment::query()->where('package_id', $package->id)->value('id');
        $this->patchJson('/api/v1/admin/appointments/'.$appointmentId.'/complete', [
            'action' => 'complete',
            'sessionNotes' => 'Finished.',
        ])->assertOk();

        $this->assertSame(1, $package->fresh()->sessions_used);
    }

    public function test_patient_cannot_start_treatment_session(): void
    {
        $patient = $this->createUser();
        $clinic = $this->createClinic(['slug' => 'no-start', 'code' => 'NO_START']);
        $service = $this->createService();
        $package = PrepaidPackage::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 1,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        $this->actingAsUser($patient);
        $this->postJson('/api/v1/admin/treatment-journeys/'.$package->id.'/sessions')->assertForbidden();
    }
}
