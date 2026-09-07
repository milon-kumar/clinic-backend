<?php

namespace Tests\Unit\Services;

use App\Models\ClinicSchedule;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\SlotHoldService;
use Carbon\Carbon;
use Tests\PlatformTestCase;

class SlotHoldServiceTest extends PlatformTestCase
{
    public function test_create_and_release_hold(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['duration_minutes' => 60]);

        $slotDate = now()->next(Carbon::MONDAY)->setTime(10, 0);

        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        $hold = app(SlotHoldService::class)->createHold(
            $clinic->id,
            $service->id,
            $slotDate->toIso8601String()
        );

        $this->assertArrayHasKey('holdId', $hold);

        $released = app(SlotHoldService::class)->releaseHold($hold['holdId']);
        $this->assertTrue($released);
    }

    public function test_confirm_hold_creates_appointment(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService(['duration_minutes' => 60]);
        $user = $this->createUser();

        $slotDate = now()->next(Carbon::TUESDAY)->setTime(11, 0);

        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        $hold = app(SlotHoldService::class)->createHold(
            $clinic->id,
            $service->id,
            $slotDate->toIso8601String(),
            $user->id
        );

        $appointment = app(SlotHoldService::class)->confirmHold($hold['holdId'], [
            'full_name' => 'Test Patient',
            'phone' => '555-0100',
            'email' => 'patient@example.com',
        ]);

        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame($clinic->id, $appointment->clinic_id);
    }
}
