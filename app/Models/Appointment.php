<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'customer_id',
        'clinic_id',
        'service_id',
        'package_id',
        'full_name',
        'phone',
        'email',
        'notes',
        'appointment_date',
        'appointment_time',
        'status',
        'payment_status',
        'payment_method',
        'amount_pence',
        'qr_token',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
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

    public function prepaidPackage(): BelongsTo
    {
        return $this->belongsTo(PrepaidPackage::class, 'package_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'customerName' => $this->full_name,
            'fullName' => $this->full_name,
            'customerEmail' => $this->email,
            'email' => $this->email,
            'phone' => $this->phone,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'serviceId' => $this->service_id,
            'serviceName' => $this->service?->name,
            'treatment' => [
                'id' => $this->service?->id,
                'title' => $this->service?->name,
            ],
            'packageId' => $this->package_id,
            'appointmentDate' => $this->appointment_date?->toDateString(),
            'appointmentTime' => $this->appointment_time,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'amountPence' => (int) $this->amount_pence,
            'amount' => ((int) $this->amount_pence) / 100,
            'isFree' => (int) $this->amount_pence === 0,
            'notes' => $this->notes,
            'qrToken' => $this->qr_token,
        ];
    }
}
