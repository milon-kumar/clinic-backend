<?php

namespace Tests\Feature;

use App\Models\ClinicStaff;
use App\Models\Order;
use Tests\PlatformTestCase;

class AdminOrgApiTest extends PlatformTestCase
{
    public function test_patient_cannot_view_reports(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'patient']));

        $this->getJson('/api/v1/admin/reports')->assertForbidden();
    }

    public function test_superadmin_sees_revenue_for_every_branch(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'code' => 'RDG', 'slug' => 'reading-rev']);
        $london = $this->createClinic(['name' => 'London', 'code' => 'LON', 'slug' => 'london-rev']);
        $customer = $this->createUser();

        Order::create([
            'customer_id' => $customer->id,
            'clinic_id' => $reading->id,
            'status' => 'paid',
            'subtotal_pence' => 10000,
            'total_pence' => 10000,
            'paid_at' => now(),
        ]);
        Order::create([
            'customer_id' => $customer->id,
            'clinic_id' => $london->id,
            'status' => 'paid',
            'subtotal_pence' => 25000,
            'total_pence' => 25000,
            'paid_at' => now(),
        ]);

        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.orders.revenuePence', 35000)
            ->assertJsonCount(2, 'data.clinics');
    }

    public function test_branch_staff_only_see_their_clinic_revenue(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'code' => 'RDG2', 'slug' => 'reading-scope']);
        $london = $this->createClinic(['name' => 'London', 'code' => 'LON2', 'slug' => 'london-scope']);
        $customer = $this->createUser();

        Order::create([
            'customer_id' => $customer->id,
            'clinic_id' => $reading->id,
            'status' => 'paid',
            'total_pence' => 10000,
            'paid_at' => now(),
        ]);
        Order::create([
            'customer_id' => $customer->id,
            'clinic_id' => $london->id,
            'status' => 'paid',
            'total_pence' => 25000,
            'paid_at' => now(),
        ]);

        $receptionist = $this->createUser([
            'role' => 'receptionist',
            'clinic_id' => $reading->id,
        ]);
        ClinicStaff::create([
            'clinic_id' => $reading->id,
            'user_id' => $receptionist->id,
            'is_active' => true,
        ]);

        $this->actingAsUser($receptionist);

        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.orders.revenuePence', 10000)
            ->assertJsonCount(1, 'data.clinics')
            ->assertJsonPath('data.clinics.0.id', $reading->id);
    }

    public function test_superadmin_assigns_staff_and_schedules_a_shift(): void
    {
        $clinic = $this->createClinic();
        $practitioner = $this->createUser(['role' => 'practitioner']);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/staff", [
            'userId' => $practitioner->id,
            'jobTitle' => 'Laser therapist',
        ])->assertCreated()
            ->assertJsonPath('data.userId', $practitioner->id);

        $this->postJson('/api/v1/admin/staff-schedules', [
            'clinicId' => $clinic->id,
            'userId' => $practitioner->id,
            'dayOfWeek' => 1,
            'startTime' => '09:00',
            'endTime' => '17:00',
        ])->assertCreated()
            ->assertJsonPath('data.dayOfWeek', 1);

        $this->getJson("/api/v1/admin/clinics/{$clinic->id}/staff")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_receptionist_cannot_create_a_branch(): void
    {
        $clinic = $this->createClinic();
        $this->actingAsUser($this->createUser([
            'role' => 'receptionist',
            'clinic_id' => $clinic->id,
        ]));

        $this->postJson('/api/v1/admin/clinics', [
            'name' => 'New Branch',
        ])->assertForbidden();
    }

    public function test_roles_catalog_is_visible_to_staff(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonFragment(['id' => 'superadmin'])
            ->assertJsonFragment(['id' => 'manager']);
    }
}
