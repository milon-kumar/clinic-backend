<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepaidPackage extends Model
{
    protected $fillable = [
        'customer_id',
        'clinic_id',
        'service_id',
        'order_id',
        'sessions_total',
        'sessions_used',
        'status',
        'expires_at',
        'next_appointment_date',
        'next_appointment_time',
        'next_appointment_id',
        'next_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'next_appointment_date' => 'date',
            'next_notified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->sessions_used >= $this->sessions_total) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function sessionsRemaining(): int
    {
        return max(0, $this->sessions_total - $this->sessions_used);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'serviceId' => $this->service_id,
            'serviceName' => $this->service?->name,
            'sessionsTotal' => $this->sessions_total,
            'sessionsUsed' => $this->sessions_used,
            'sessionsRemaining' => $this->sessionsRemaining(),
            'status' => $this->status,
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'nextAppointmentId' => $this->next_appointment_id,
            'nextAppointmentDate' => $this->next_appointment_date?->toDateString(),
            'nextAppointmentTime' => $this->next_appointment_time,
        ];
    }
}
