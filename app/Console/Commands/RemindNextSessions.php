<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\ClientNotifyService;
use Illuminate\Console\Command;

class RemindNextSessions extends Command
{
    protected $signature = 'sessions:remind-next';

    protected $description = 'Email clients about sessions booked for tomorrow';

    public function handle(ClientNotifyService $notify): int
    {
        $tomorrow = now()->addDay()->toDateString();
        $appointments = Appointment::query()
            ->with(['clinic', 'service', 'customer', 'prepaidPackage'])
            ->whereDate('appointment_date', $tomorrow)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get();

        $sent = 0;
        foreach ($appointments as $appointment) {
            if ($appointment->prepaidPackage?->next_notified_at?->isToday()) {
                continue;
            }
            if ($notify->nextSession($appointment)) {
                $sent++;
            }
        }

        $this->info("Sent {$sent} next-session emails.");

        return self::SUCCESS;
    }
}
