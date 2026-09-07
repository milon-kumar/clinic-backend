<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\ClinicSchedule;
use App\Models\PrepaidPackage;
use App\Models\ServicePrerequisite;
use App\Services\PrerequisiteService;
use Carbon\Carbon;
use Tests\PlatformTestCase;

class PrerequisiteServiceTest extends PlatformTestCase
{
    public function test_check_returns_satisfied_when_no_prerequisites(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $service = $this->createService();

        $result = app(PrerequisiteService::class)->check($user->id, $service->id, $clinic->id);

        $this->assertTrue($result['satisfied']);
        $this->assertEmpty($result['missing']);
    }

    public function test_check_requires_completed_consultation(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $consult = $this->createService(['slug' => 'consult']);
        $treatment = $this->createService(['slug' => 'advanced']);

        ServicePrerequisite::create([
            'service_id' => $treatment->id,
            'prerequisite_service_id' => $consult->id,
            'rule' => 'must_complete',
        ]);

        $result = app(PrerequisiteService::class)->check($user->id, $treatment->id, $clinic->id);

        $this->assertFalse($result['satisfied']);
        $this->assertCount(1, $result['missing']);

        Appointment::create([
            'customer_id' => $user->id,
            'clinic_id' => $clinic->id,
            'service_id' => $consult->id,
            'full_name' => $user->name,
            'phone' => '123',
            'appointment_date' => now(),
            'appointment_time' => '10:00 AM',
            'status' => 'completed',
        ]);

        $result = app(PrerequisiteService::class)->check($user->id, $treatment->id, $clinic->id);

        $this->assertTrue($result['satisfied']);
    }

    public function test_check_requires_active_package(): void
    {
        $user = $this->createUser();
        $clinic = $this->createClinic();
        $base = $this->createService(['slug' => 'base']);
        $addon = $this->createService(['slug' => 'addon']);

        ServicePrerequisite::create([
            'service_id' => $addon->id,
            'prerequisite_service_id' => $base->id,
            'rule' => 'must_purchase',
        ]);

        $result = app(PrerequisiteService::class)->check($user->id, $addon->id, $clinic->id);
        $this->assertFalse($result['satisfied']);

        PrepaidPackage::create([
            'customer_id' => $user->id,
            'clinic_id' => $clinic->id,
            'service_id' => $base->id,
            'sessions_total' => 1,
            'sessions_used' => 0,
            'status' => 'active',
        ]);

        $result = app(PrerequisiteService::class)->check($user->id, $addon->id, $clinic->id);
        $this->assertTrue($result['satisfied']);
    }
}
