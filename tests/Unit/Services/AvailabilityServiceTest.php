<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\ClinicSchedule;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Tests\PlatformTestCase;

class AvailabilityServiceTest extends PlatformTestCase
{
    public function test_get_slots_excludes_booked_appointments(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();

        $date = now()->next(Carbon::MONDAY)->startOfDay();

        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $date->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        Appointment::create([
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'full_name' => 'Jane Doe',
            'phone' => '123',
            'appointment_date' => $date->toDateString(),
            'appointment_time' => '10:00 AM',
            'status' => 'confirmed',
        ]);

        $slots = app(AvailabilityService::class)->getSlots(
            $clinic->id,
            [$service->id],
            $date,
            $date->copy()->endOfDay()
        );

        $labels = $slots->pluck('timeLabel')->all();

        $this->assertNotContains('10:00 AM', $labels);
        $this->assertContains('09:00 AM', $labels);
    }

    public function test_time_of_day_filter(): void
    {
        $clinic = $this->createClinic();
        $service = $this->createService();
        $date = now()->next(Carbon::MONDAY)->startOfDay();

        $slots = app(AvailabilityService::class)->getSlots(
            $clinic->id,
            [$service->id],
            $date,
            $date->copy()->endOfDay(),
            'Morning'
        );

        foreach ($slots as $slot) {
            $this->assertSame('Morning', $slot['timeOfDay']);
        }
    }
}
