<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicClosure;
use App\Models\ClinicSchedule;
use App\Models\SlotHold;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const TIME_SLOTS = [
        '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
        '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM',
        '05:00 PM', '06:00 PM', '07:00 PM', '08:00 PM', '09:00 PM',
    ];

    /**
     * @param  array<int>  $serviceIds
     * @return Collection<int, array<string, mixed>>
     */
    public function getSlots(
        int $clinicId,
        array $serviceIds,
        Carbon $from,
        Carbon $to,
        ?string $timeOfDay = null
    ): Collection {
        $schedules = ClinicSchedule::query()
            ->where('clinic_id', $clinicId)
            ->get()
            ->keyBy('day_of_week');

        $closures = ClinicClosure::query()
            ->where('clinic_id', $clinicId)
            ->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->get();

        $bookedTimes = $this->getBookedSlotKeys($clinicId, $from, $to);
        $heldTimes = $this->getHeldSlotKeys($clinicId, $from, $to);

        $slots = collect();
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->endOfDay());

        foreach ($period as $date) {
            $dayOfWeek = $date->dayOfWeek;
            $schedule = $schedules->get($dayOfWeek);

            if (! $schedule) {
                continue;
            }

            if ($this->isClosedOnDate($closures, $date)) {
                continue;
            }

            foreach (self::TIME_SLOTS as $timeLabel) {
                $slotStart = Carbon::parse($date->format('Y-m-d').' '.$timeLabel);
                $slotKey = $slotStart->format('Y-m-d H:i');

                if ($slotStart->lt($from) || $slotStart->gt($to)) {
                    continue;
                }

                if (isset($bookedTimes[$slotKey]) || isset($heldTimes[$slotKey])) {
                    continue;
                }

                $bucket = $this->timeOfDayBucket($slotStart);

                if ($timeOfDay && strtolower($timeOfDay) !== strtolower($bucket)) {
                    continue;
                }

                $slots->push([
                    'startsAt' => $slotStart->toIso8601String(),
                    'timeLabel' => $timeLabel,
                    'date' => $date->format('Y-m-d'),
                    'timeOfDay' => $bucket,
                    'clinicId' => $clinicId,
                    'serviceIds' => $serviceIds,
                    'available' => true,
                ]);
            }
        }

        return $slots->values();
    }

    /**
     * @return array<string, true>
     */
    private function getBookedSlotKeys(int $clinicId, Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $keys = [];
        foreach ($appointments as $appointment) {
            $key = Carbon::parse($appointment->appointment_date->format('Y-m-d').' '.$appointment->appointment_time)
                ->format('Y-m-d H:i');
            $keys[$key] = true;
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    private function getHeldSlotKeys(int $clinicId, Carbon $from, Carbon $to): array
    {
        $holds = SlotHold::query()
            ->where('clinic_id', $clinicId)
            ->where('expires_at', '>', now())
            ->whereNull('appointment_id')
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $keys = [];
        foreach ($holds as $hold) {
            $keys[$hold->starts_at->format('Y-m-d H:i')] = true;
        }

        return $keys;
    }

    private function isClosedOnDate(Collection $closures, Carbon $date): bool
    {
        foreach ($closures as $closure) {
            if ($date->between($closure->starts_at->startOfDay(), $closure->ends_at->endOfDay())) {
                return true;
            }
        }

        return false;
    }

    private function timeOfDayBucket(Carbon $time): string
    {
        $hour = (int) $time->format('H');

        return match (true) {
            $hour < 12 => 'Morning',
            $hour < 15 => 'Midday',
            $hour < 18 => 'Afternoon',
            default => 'Evening',
        };
    }
}
