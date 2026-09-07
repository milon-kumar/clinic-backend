<?php

namespace Tests\Feature;

use App\Models\ClinicSchedule;
use App\Models\PrepaidPackage;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class BookingApiTest extends PlatformTestCase
{
    public function test_hold_and_confirm_appointment(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService(['duration_minutes' => 60]);
        $slotDate = now()->next(Carbon::WEDNESDAY)->setTime(10, 0);

        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        Sanctum::actingAs($user);

        $hold = $this->postJson('/api/v1/book/holds', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'startsAt' => $slotDate->toIso8601String(),
        ]);

        $hold->assertCreated();
        $holdId = $hold->json('data.holdId');

        $confirm = $this->postJson('/api/v1/book/appointments/confirm', [
            'holdId' => $holdId,
            'fullName' => 'Jane Patient',
            'phone' => '555-0100',
            'email' => $user->email,
            'paymentMethod' => 'cash',
        ]);

        $confirm->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.amount', 0)
            ->assertJsonPath('data.isFree', true)
            ->assertJsonPath('data.paymentMethod', 'free');
        $this->assertDatabaseHas('appointments', [
            'customer_id' => $user->id,
            'clinic_id' => $clinic->id,
            'status' => 'confirmed',
            'amount_pence' => 0,
        ]);
    }

    public function test_paid_appointment_uses_flat_appointment_amount(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic(['slug' => 'paid-book', 'code' => 'PAID_BOOK']);
        $service = $this->createService([
            'duration_minutes' => 60,
            'appointment_amount_pence' => 7500,
        ]);
        $slotDate = now()->next(Carbon::FRIDAY)->setTime(10, 0);

        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        Sanctum::actingAs($user);

        $hold = $this->postJson('/api/v1/book/holds', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'startsAt' => $slotDate->toIso8601String(),
        ])->assertCreated();

        $this->postJson('/api/v1/book/appointments/confirm', [
            'holdId' => $hold->json('data.holdId'),
            'fullName' => 'Jane Patient',
            'phone' => '555-0100',
            'email' => $user->email,
            'paymentMethod' => 'cash',
        ])->assertCreated()
            ->assertJsonPath('data.amount', 75)
            ->assertJsonPath('data.isFree', false)
            ->assertJsonPath('data.paymentMethod', 'cash');
    }

    public function test_cannot_redeem_package_at_wrong_clinic(): void
    {
        $user = $this->createUser();
        $reading = $this->createClinic(['slug' => 'reading-book', 'code' => 'READ_BOOK']);
        $london = $this->createClinic(['slug' => 'london-book', 'code' => 'LON_BOOK']);
        $service = $this->createService(['duration_minutes' => 60]);
        $slotDate = now()->next(Carbon::THURSDAY)->setTime(11, 0);

        ClinicSchedule::create([
            'clinic_id' => $london->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        $package = PrepaidPackage::create([
            'customer_id' => $user->id,
            'clinic_id' => $reading->id,
            'service_id' => $service->id,
            'sessions_total' => 3,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $hold = $this->postJson('/api/v1/book/holds', [
            'clinicId' => $london->id,
            'serviceId' => $service->id,
            'startsAt' => $slotDate->toIso8601String(),
        ])->assertCreated();

        $this->postJson('/api/v1/book/appointments/confirm', [
            'holdId' => $hold->json('data.holdId'),
            'fullName' => 'Jane',
            'phone' => '555',
            'packageId' => $package->id,
        ])->assertStatus(422);
    }

    public function test_staff_required_for_admin_routes(): void
    {
        $patient = $this->createUser(['role' => 'patient']);
        Sanctum::actingAs($patient);
        $this->getJson('/api/v1/admin/appointments')->assertForbidden();

        $admin = $this->createUser(['role' => 'admin', 'email' => 'admin-api@example.com', 'username' => 'adminapi']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/appointments/stats')->assertOk();
    }
}
