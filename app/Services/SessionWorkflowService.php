<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PrepaidPackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SessionWorkflowService
{
    public function __construct(
        private ClientNotifyService $notify,
        private NotificationService $notifications,
    ) {}

    /**
     * @return array{appointment: Appointment, next: ?Appointment}
     */
    public function complete(Appointment $appointment, ?string $nextDate = null, ?string $nextTime = null): array
    {
        $appointment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        try {
            $this->notifications->sessionCompleted($appointment->fresh(['clinic', 'service']));
        } catch (\Throwable $e) {
            Log::warning('In-app notification failed', ['error' => $e->getMessage()]);
        }

        $next = null;
        if ($nextDate && $nextTime) {
            $next = $this->scheduleNext($appointment->fresh(['clinic', 'service', 'prepaidPackage']), $nextDate, $nextTime);
        }

        return [
            'appointment' => $appointment->fresh(['clinic', 'service', 'nextAppointment', 'prepaidPackage']),
            'next' => $next,
        ];
    }

    public function defer(Appointment $appointment, ?string $nextDate = null, ?string $nextTime = null): Appointment
    {
        $date = $nextDate ?: now()->addDay()->toDateString();
        $time = $nextTime ?: $appointment->appointment_time;

        $appointment->update([
            'appointment_date' => Carbon::parse($date)->toDateString(),
            'appointment_time' => $time,
            'status' => $appointment->status === 'cancelled' ? 'confirmed' : $appointment->status,
        ]);

        $this->syncPackageNext($appointment->fresh(['prepaidPackage']), $date, $time, $appointment->id);
        $this->notify->nextSession($appointment->fresh(['clinic', 'service', 'customer']));

        return $appointment->fresh(['clinic', 'service', 'nextAppointment', 'prepaidPackage']);
    }

    public function scheduleNext(Appointment $from, string $date, string $time): Appointment
    {
        $next = Appointment::create([
            'customer_id' => $from->customer_id,
            'clinic_id' => $from->clinic_id,
            'service_id' => $from->service_id,
            'package_id' => $from->package_id,
            'full_name' => $from->full_name,
            'phone' => $from->phone,
            'email' => $from->email,
            'notes' => $from->notes,
            'appointment_date' => Carbon::parse($date)->toDateString(),
            'appointment_time' => $time,
            'status' => 'confirmed',
            'amount_pence' => 0,
            'payment_method' => $from->package_id ? 'package' : ($from->payment_method ?: 'cash'),
            'payment_status' => $from->package_id ? 'pending_session' : 'scheduled',
            'qr_token' => (string) Str::uuid(),
        ]);

        $from->update(['next_appointment_id' => $next->id]);
        $this->syncPackageNext($from, $date, $time, $next->id);
        $this->notify->nextSession($next->load(['clinic', 'service', 'customer']));

        return $next;
    }

    private function syncPackageNext(Appointment $appointment, string $date, string $time, int $nextId): void
    {
        if (! $appointment->package_id) {
            return;
        }

        PrepaidPackage::query()->where('id', $appointment->package_id)->update([
            'next_appointment_date' => Carbon::parse($date)->toDateString(),
            'next_appointment_time' => $time,
            'next_appointment_id' => $nextId,
        ]);
    }
}
