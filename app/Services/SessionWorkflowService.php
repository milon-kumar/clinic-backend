<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PrepaidPackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SessionWorkflowService
{
    public function __construct(
        private ClientNotifyService $notify,
        private NotificationService $notifications,
        private PackageService $packages,
    ) {}

    /**
     * @return array{appointment: Appointment, next: ?Appointment}
     */
    public function complete(
        Appointment $appointment,
        ?string $nextDate = null,
        ?string $nextTime = null,
        ?string $sessionNotes = null,
    ): array {
        $updates = [
            'status' => 'completed',
            'completed_at' => now(),
        ];

        if ($sessionNotes !== null && Schema::hasColumn('appointments', 'session_notes')) {
            $updates['session_notes'] = $sessionNotes;
        }

        $appointment->update($updates);
        $this->syncPackageUsage($appointment->fresh());

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
        try {
            $this->notify->nextSession($next->load(['clinic', 'service', 'customer']));
        } catch (\Throwable $e) {
            Log::warning('Next session notification failed', ['error' => $e->getMessage()]);
        }

        return $next;
    }

    /**
     * Start a walk-in session from a prepaid treatment order that has remaining visits
     * but no booked appointment yet.
     *
     * @return array{appointment: Appointment, next: ?Appointment}
     */
    public function startFromPackage(
        PrepaidPackage $package,
        ?string $date = null,
        ?string $time = null,
        ?string $sessionNotes = null,
        bool $complete = false,
        ?string $nextDate = null,
        ?string $nextTime = null,
    ): array {
        $package->loadMissing(['customer', 'clinic', 'service']);

        if (! $package->isRedeemable()) {
            abort(422, 'No treatments left on this order.');
        }

        if (! $package->customer) {
            abort(422, 'This order has no customer.');
        }

        $openCount = Appointment::query()
            ->where('package_id', $package->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        if ($openCount + $package->sessions_used >= $package->sessions_total) {
            abort(422, 'All remaining treatments on this order are already in progress.');
        }

        $starts = Carbon::parse(($date ?: now()->toDateString()).' '.($time ?: now()->format('H:i')));
        $customer = $package->customer;

        $appointment = Appointment::create([
            'customer_id' => $package->customer_id,
            'clinic_id' => $package->clinic_id,
            'service_id' => $package->service_id,
            'package_id' => $package->id,
            'full_name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'appointment_date' => $starts->toDateString(),
            'appointment_time' => $starts->format('H:i'),
            'status' => 'confirmed',
            'amount_pence' => 0,
            'payment_method' => 'package',
            'payment_status' => 'package',
            'qr_token' => (string) Str::uuid(),
        ]);

        if ($complete) {
            return $this->complete($appointment, $nextDate, $nextTime, $sessionNotes);
        }

        if ($sessionNotes !== null && Schema::hasColumn('appointments', 'session_notes')) {
            $appointment->update(['session_notes' => $sessionNotes]);
        }

        return [
            'appointment' => $appointment->fresh(['clinic', 'service', 'nextAppointment', 'prepaidPackage', 'customer']),
            'next' => null,
        ];
    }

    private function syncPackageUsage(Appointment $appointment): void
    {
        if (! $appointment->package_id) {
            return;
        }

        DB::transaction(function () use ($appointment) {
            $package = PrepaidPackage::query()->lockForUpdate()->find($appointment->package_id);
            if (! $package) {
                return;
            }

            $completed = Appointment::query()
                ->where('package_id', $package->id)
                ->where('status', 'completed')
                ->count();

            if ($completed > $package->sessions_used) {
                $package->increment('sessions_used', $completed - $package->sessions_used);
                $package = $package->fresh();
            }

            if ($package && $package->sessionsRemaining() <= 0 && $package->status === 'active') {
                $package->update(['status' => 'exhausted']);
            }
        });
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
