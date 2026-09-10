<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\User;
use App\Support\Roles;

class NotificationService
{
    public function bookingConfirmed(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'service', 'customer']);
        $treatment = $appointment->service?->name ?: 'your treatment';
        $clinic = $appointment->clinic?->name ?: 'the clinic';
        $when = trim(($appointment->appointment_date?->toFormattedDateString() ?: '').' '.$appointment->appointment_time);

        $this->notifyCustomer($appointment->customer_id, [
            'type' => 'booking_confirmed',
            'title' => 'Booking confirmed',
            'body' => "{$treatment} at {$clinic} on {$when}.",
            'href' => '/account/appointments',
            'clinic_id' => $appointment->clinic_id,
            'data' => ['appointmentId' => $appointment->id],
        ]);

        $who = $appointment->full_name ?: $appointment->customer?->name ?: 'A client';
        $this->notifyStaff((int) $appointment->clinic_id, [
            'type' => 'new_booking',
            'title' => 'New booking',
            'body' => "{$who} booked {$treatment} for {$when}.",
            'href' => '/Admin/appointments',
            'data' => ['appointmentId' => $appointment->id],
        ], $appointment->customer_id);
    }

    public function purchaseConfirmed(Order $order): void
    {
        $order->loadMissing(['customer', 'clinic', 'packages.service', 'lines.service']);
        $clinic = $order->clinic?->name ?: 'your clinic';
        $sessions = $order->packages->sum('sessions_total') ?: $order->lines->sum('quantity');

        $this->notifyCustomer($order->customer_id, [
            'type' => 'purchase_confirmed',
            'title' => 'Purchase confirmed',
            'body' => "You bought {$sessions} prepaid session".($sessions === 1 ? '' : 's')." at {$clinic}.",
            'href' => '/account/packages',
            'clinic_id' => $order->clinic_id,
            'data' => ['orderId' => $order->id],
        ]);

        $who = $order->customer?->name ?: $order->customer?->email ?: 'A client';
        $this->notifyStaff((int) $order->clinic_id, [
            'type' => 'new_order',
            'title' => 'New package purchase',
            'body' => "{$who} bought {$sessions} session".($sessions === 1 ? '' : 's').'.',
            'href' => '/Admin/orders',
            'data' => ['orderId' => $order->id],
        ], $order->customer_id);
    }

    public function nextSession(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'service', 'customer']);
        $treatment = $appointment->service?->name ?: 'your treatment';
        $when = trim(($appointment->appointment_date?->toFormattedDateString() ?: '').' '.$appointment->appointment_time);

        $this->notifyCustomer($appointment->customer_id, [
            'type' => 'next_session',
            'title' => 'Next session booked',
            'body' => "{$treatment} is scheduled for {$when}.",
            'href' => '/account/appointments',
            'clinic_id' => $appointment->clinic_id,
            'data' => ['appointmentId' => $appointment->id],
        ]);

        $who = $appointment->full_name ?: $appointment->customer?->name ?: 'A client';
        $this->notifyStaff((int) $appointment->clinic_id, [
            'type' => 'next_session',
            'title' => 'Next session set',
            'body' => "{$who}: {$treatment} on {$when}.",
            'href' => '/Admin/appointments',
            'data' => ['appointmentId' => $appointment->id],
        ], $appointment->customer_id);
    }

    public function sessionCompleted(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'service']);
        $treatment = $appointment->service?->name ?: 'your treatment';

        $this->notifyCustomer($appointment->customer_id, [
            'type' => 'session_completed',
            'title' => 'Session completed',
            'body' => "Your {$treatment} visit has been marked complete.",
            'href' => '/account/appointments',
            'clinic_id' => $appointment->clinic_id,
            'data' => ['appointmentId' => $appointment->id],
        ]);
    }

    public function appointmentCancelled(Appointment $appointment): void
    {
        $appointment->loadMissing(['clinic', 'service']);
        $treatment = $appointment->service?->name ?: 'your treatment';

        $this->notifyCustomer($appointment->customer_id, [
            'type' => 'appointment_cancelled',
            'title' => 'Appointment cancelled',
            'body' => "Your {$treatment} appointment has been cancelled.",
            'href' => '/account/appointments',
            'clinic_id' => $appointment->clinic_id,
            'data' => ['appointmentId' => $appointment->id],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function notifyCustomer(?int $userId, array $attrs): ?AppNotification
    {
        if (! $userId) {
            return null;
        }

        return $this->create($userId, 'customer', $attrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function notifyStaff(int $clinicId, array $attrs, ?int $exceptUserId = null): void
    {
        $staff = User::query()
            ->whereIn('role', Roles::staff())
            ->where(function ($query) use ($clinicId) {
                $query->whereIn('role', [Roles::SUPERADMIN, Roles::ADMIN])
                    ->orWhere('clinic_id', $clinicId)
                    ->orWhereHas('staffAssignments', function ($assignment) use ($clinicId) {
                        $assignment->where('clinic_id', $clinicId)->where('is_active', true);
                    });
            })
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->distinct()
            ->pluck('id');

        foreach ($staff as $userId) {
            $this->create((int) $userId, 'staff', $attrs + ['clinic_id' => $clinicId ?: null]);
        }
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function create(int $userId, string $audience, array $attrs): AppNotification
    {
        return AppNotification::create([
            'user_id' => $userId,
            'audience' => $audience,
            'type' => $attrs['type'],
            'title' => $attrs['title'],
            'body' => $attrs['body'] ?? null,
            'href' => $attrs['href'] ?? null,
            'clinic_id' => $attrs['clinic_id'] ?? null,
            'data' => $attrs['data'] ?? null,
        ]);
    }
}
