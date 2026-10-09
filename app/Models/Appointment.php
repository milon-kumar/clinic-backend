<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $fillable = [
        'customer_id',
        'clinic_id',
        'service_id',
        'package_id',
        'next_appointment_id',
        'full_name',
        'phone',
        'email',
        'notes',
        'question_answers',
        'session_notes',
        'appointment_date',
        'appointment_time',
        'status',
        'payment_status',
        'payment_method',
        'amount_pence',
        'qr_token',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'completed_at' => 'datetime',
            'question_answers' => 'array',
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

    public function nextAppointment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_appointment_id');
    }

    public function previousAppointment(): HasOne
    {
        return $this->hasOne(self::class, 'next_appointment_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function intake(): HasOne
    {
        return $this->hasOne(TreatmentIntake::class);
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
            'sessionsTotal' => $this->prepaidPackage?->sessions_total,
            'sessionsUsed' => $this->prepaidPackage?->sessions_used,
            'sessionsRemaining' => $this->prepaidPackage?->sessionsRemaining(),
            'appointmentDate' => $this->appointment_date?->toDateString(),
            'appointmentTime' => $this->appointment_time,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'nextAppointmentId' => $this->next_appointment_id,
            'nextAppointmentDate' => $this->nextAppointment?->appointment_date?->toDateString()
                ?? $this->prepaidPackage?->next_appointment_date,
            'nextAppointmentTime' => $this->nextAppointment?->appointment_time
                ?? $this->prepaidPackage?->next_appointment_time,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'amountPence' => (int) $this->amount_pence,
            'amount' => ((int) $this->amount_pence) / 100,
            'isFree' => (int) $this->amount_pence === 0,
            'notes' => $this->notes,
            ...$this->questionPreview(),
            'sessionNotes' => $this->session_notes,
            'previousSessionNotes' => $this->relationLoaded('previousAppointment')
                ? $this->previousAppointment?->session_notes
                : null,
            'previousAppointmentId' => $this->relationLoaded('previousAppointment')
                ? $this->previousAppointment?->id
                : null,
            'qrToken' => $this->qr_token,
            'reviewId' => $this->relationLoaded('review') ? $this->review?->id : null,
            'canReview' => $this->status === 'completed'
                && (! $this->relationLoaded('review') || $this->review === null),
        ];
    }

    /**
     * @return array{questionAnswers: list<array<string, mixed>>, questionUrl: ?string, questionStatus: ?string}
     */
    private function questionPreview(): array
    {
        $stored = collect($this->question_answers ?: [])
            ->filter(fn ($row) => is_array($row) && filled($row['prompt'] ?? null))
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'prompt' => $row['prompt'],
                'answerType' => $row['answerType'] ?? 'text',
                'options' => array_values($row['options'] ?? []),
                'required' => (bool) ($row['required'] ?? false),
                'answer' => $row['answer'] ?? null,
            ])
            ->values();

        $intake = $this->relationLoaded('intake') ? $this->intake : null;
        if ($intake && $intake->relationLoaded('answers')) {
            $known = $intake->answers
                ->map(fn (TreatmentIntakeAnswer $row) => mb_strtolower(trim($row->prompt)))
                ->all();
            $extras = $stored
                ->reject(fn ($row) => in_array(mb_strtolower(trim((string) $row['prompt'])), $known, true))
                ->values();

            return [
                'questionAnswers' => $intake->answers
                    ->map(fn (TreatmentIntakeAnswer $row) => $row->toApi())
                    ->concat($extras)
                    ->values()
                    ->all(),
                'questionUrl' => $intake->publicUrl(),
                'questionStatus' => $intake->submitted_at ? 'submitted' : 'pending',
                'signature' => $intake->signature,
                'signedName' => $intake->signed_name,
                'signedAt' => $intake->signed_at?->toIso8601String(),
            ];
        }

        $filled = $stored->contains(fn ($row) => filled($row['answer'] ?? null));

        return [
            'questionAnswers' => $stored->all(),
            'questionUrl' => null,
            'questionStatus' => $filled ? 'submitted' : null,
            'signature' => null,
            'signedName' => null,
            'signedAt' => null,
        ];
    }
}
