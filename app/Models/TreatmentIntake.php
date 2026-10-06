<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentIntake extends Model
{
    protected $fillable = [
        'prepaid_package_id',
        'appointment_id',
        'order_id',
        'token',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PrepaidPackage::class, 'prepaid_package_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TreatmentIntakeAnswer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function publicUrl(): string
    {
        $raw = (string) config('app.frontend_url', 'http://localhost:3000');
        $base = rtrim(trim(explode(',', $raw)[0]), '/');

        return $base.'/intake/'.$this->token;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'status' => $this->submitted_at ? 'submitted' : 'pending',
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'token' => $this->token,
            'url' => $this->publicUrl(),
            'questions' => $this->relationLoaded('answers')
                ? $this->answers->map(fn (TreatmentIntakeAnswer $row) => $row->toApi())->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicApi(): array
    {
        $this->loadMissing(['answers', 'package.service', 'package.clinic', 'appointment.service', 'appointment.clinic']);

        return [
            'treatmentName' => $this->package?->service?->name
                ?: $this->appointment?->service?->name
                ?: 'Treatment',
            'clinicName' => $this->package?->clinic?->name ?: $this->appointment?->clinic?->name,
            'orderId' => $this->order_id,
            'status' => $this->submitted_at ? 'submitted' : 'pending',
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'questions' => $this->answers->map(fn (TreatmentIntakeAnswer $row) => $row->toApi())->values()->all(),
        ];
    }
}
