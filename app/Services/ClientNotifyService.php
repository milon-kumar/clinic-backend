<?php

namespace App\Services;

use App\Mail\BookingConfirmedMail;
use App\Mail\NextSessionMail;
use App\Mail\PurchaseConfirmedMail;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;

class ClientNotifyService
{
    public function __construct(
        private MailConfigService $mail,
        private NotificationService $notifications,
    ) {}

    public function bookingConfirmed(Appointment $appointment): bool
    {
        $appointment->loadMissing(['clinic', 'service']);
        $this->safeNotify(fn () => $this->notifications->bookingConfirmed($appointment));
        $to = $this->recipient($appointment->email, $appointment->customer?->email);
        if (! $to) {
            return false;
        }

        return $this->mail->send(new BookingConfirmedMail($this->appointmentPayload($appointment)), $to);
    }

    public function purchaseConfirmed(Order $order): bool
    {
        $order->loadMissing(['customer', 'clinic', 'lines.service', 'packages.service', 'packages.clinic']);
        $this->safeNotify(fn () => $this->notifications->purchaseConfirmed($order));
        $to = $this->recipient($order->customer?->email);
        if (! $to) {
            return false;
        }

        $packages = $order->packages->isNotEmpty()
            ? $order->packages
            : $order->lines;

        return $this->mail->send(new PurchaseConfirmedMail([
            'siteName' => $this->siteName(),
            'customerName' => $order->customer?->name ?: $order->customer?->email ?: 'there',
            'orderId' => $order->id,
            'clinicName' => $order->clinic?->name ?: 'your clinic',
            'total' => ((int) $order->total_pence) / 100,
            'packages' => $packages->map(fn ($row) => [
                'name' => $row->service?->name ?: 'Treatment',
                'sessions' => (int) ($row->sessions_total ?? $row->quantity ?? 1),
                'clinic' => $row->clinic?->name ?? $order->clinic?->name ?? 'your clinic',
            ])->all(),
        ]), $to);
    }

    public function nextSession(Appointment $appointment): bool
    {
        $appointment->loadMissing(['clinic', 'service', 'customer']);
        $this->safeNotify(fn () => $this->notifications->nextSession($appointment));
        $to = $this->recipient($appointment->email, $appointment->customer?->email);
        if (! $to) {
            return false;
        }

        $payload = $this->appointmentPayload($appointment);
        $sent = $this->mail->send(new NextSessionMail($payload), $to);

        if ($sent && $appointment->package_id) {
            $appointment->prepaidPackage?->update(['next_notified_at' => now()]);
        }

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentPayload(Appointment $appointment): array
    {
        return [
            'siteName' => $this->siteName(),
            'customerName' => $appointment->full_name ?: $appointment->customer?->name ?: 'there',
            'treatmentName' => $appointment->service?->name ?: 'your treatment',
            'clinicName' => $appointment->clinic?->name ?: 'your clinic',
            'appointmentDate' => $appointment->appointment_date?->toFormattedDateString() ?: (string) $appointment->appointment_date,
            'appointmentTime' => $appointment->appointment_time,
            'appointmentId' => $appointment->id,
        ];
    }

    private function recipient(?string ...$emails): ?string
    {
        foreach ($emails as $email) {
            if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    private function siteName(): string
    {
        return SiteSetting::query()->value('site_name') ?: config('app.name', 'Clinic');
    }

    private function safeNotify(callable $notify): void
    {
        try {
            $notify();
        } catch (\Throwable $e) {
            Log::warning('In-app notification failed', ['error' => $e->getMessage()]);
        }
    }
}
