<?php

namespace Tests;

use App\Models\Clinic;
use App\Models\ClinicSchedule;
use App\Models\ClinicService;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

abstract class PlatformTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createClinic(array $overrides = []): Clinic
    {
        $clinic = Clinic::create(array_merge([
            'code' => 'LCUK_TEST',
            'name' => 'Test Clinic',
            'slug' => 'test-clinic-'.uniqid(),
            'region' => 'england',
            'address_json' => ['city' => 'Reading'],
            'latitude' => 51.45,
            'longitude' => -0.97,
            'phone' => '+44 118 000 0000',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ], $overrides));

        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => now()->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        return $clinic;
    }

    protected function createService(array $overrides = []): Service
    {
        $service = Service::create(array_merge([
            'sku' => 'SKU_'.uniqid(),
            'slug' => 'service-'.uniqid(),
            'name' => 'Test Service',
            'category' => 'skin',
            'treatment_type' => 'facial',
            'description' => 'Test description',
            'duration_minutes' => 60,
            'base_price_pence' => 10000,
            'supports_buy' => true,
            'supports_book' => true,
            'is_active' => true,
        ], $overrides));

        ServicePackage::create([
            'service_id' => $service->id,
            'title' => 'Single Session',
            'sessions' => 1,
            'price_pence' => 10000,
        ]);

        return $service;
    }

    protected function attachServiceToClinic(Clinic $clinic, Service $service, array $overrides = []): ClinicService
    {
        return ClinicService::create(array_merge([
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'buy_enabled' => true,
            'book_enabled' => true,
            'online_buy_enabled' => true,
            'price_pence' => null,
        ], $overrides));
    }

    /**
     * @param  array<int, int>  $clinicIds
     */
    protected function createDoctor(array $overrides = [], array $clinicIds = []): Doctor
    {
        $clinicId = $overrides['clinic_id'] ?? ($clinicIds[0] ?? null);
        $doctor = Doctor::create(array_merge([
            'name' => 'Dr Test',
            'specialty' => 'Laser',
            'bio' => 'Test bio',
            'is_active' => true,
            'clinic_id' => $clinicId,
        ], $overrides));

        if ($clinicIds) {
            $doctor->syncClinics($clinicIds);
        } elseif ($clinicId) {
            $doctor->clinics()->sync([(int) $clinicId]);
        }

        return $doctor->fresh(['clinic', 'clinics']);
    }

    protected function actingAsUser(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'user_'.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => 'password',
            'role' => 'patient',
            'is_verified' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }
}
