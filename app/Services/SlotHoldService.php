<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\SlotHold;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlotHoldService
{
    public const TTL_MINUTES = 5;

    public function __construct(
        private AvailabilityService $availabilityService,
    ) {}

    /**
     * @return array{holdId: string, expiresAt: string, startsAt: string, endsAt: string}
     */
    public function createHold(
        int $clinicId,
        int $serviceId,
        string $startsAt,
        ?int $customerId = null
    ): array {
        $service = Service::findOrFail($serviceId);
        $start = Carbon::parse($startsAt);
        $end = $start->copy()->addMinutes($service->duration_minutes);

        $available = $this->availabilityService->getSlots(
            $clinicId,
            [$serviceId],
            $start->copy()->startOfDay(),
            $start->copy()->endOfDay()
        )->first(fn ($slot) => Carbon::parse($slot['startsAt'])->equalTo($start));

        if (! $available) {
            throw new \RuntimeException('Slot is not available');
        }

        $existing = SlotHold::query()
            ->where('clinic_id', $clinicId)
            ->where('starts_at', $start)
            ->where('expires_at', '>', now())
            ->whereNull('appointment_id')
            ->exists();

        if ($existing) {
            throw new \RuntimeException('Slot is already held');
        }

        $hold = SlotHold::create([
            'clinic_id' => $clinicId,
            'service_id' => $serviceId,
            'customer_id' => $customerId,
            'starts_at' => $start,
            'ends_at' => $end,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return [
            'holdId' => $hold->id,
            'expiresAt' => $hold->expires_at->toIso8601String(),
            'startsAt' => $hold->starts_at->toIso8601String(),
            'endsAt' => $hold->ends_at->toIso8601String(),
        ];
    }

    public function releaseHold(string $holdId): bool
    {
        $hold = SlotHold::find($holdId);

        if (! $hold || $hold->appointment_id !== null) {
            return false;
        }

        return (bool) $hold->delete();
    }

    /**
     * @param  array<string, mixed>  $appointmentData
     */
    public function confirmHold(string $holdId, array $appointmentData): Appointment
    {
        return DB::transaction(function () use ($holdId, $appointmentData) {
            $hold = SlotHold::query()
                ->lockForUpdate()
                ->findOrFail($holdId);

            if (! $hold->isActive()) {
                throw new \RuntimeException('Hold has expired or is already confirmed');
            }

            $appointment = Appointment::create(array_merge([
                'clinic_id' => $hold->clinic_id,
                'service_id' => $hold->service_id,
                'customer_id' => $hold->customer_id,
                'appointment_date' => $hold->starts_at->toDateString(),
                'appointment_time' => $hold->starts_at->format('h:i A'),
                'status' => 'confirmed',
                'qr_token' => Str::uuid()->toString(),
            ], $appointmentData));

            $hold->update(['appointment_id' => $appointment->id]);

            return $appointment->load(['clinic', 'service']);
        });
    }
}
