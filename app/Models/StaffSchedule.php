<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSchedule extends Model
{
    protected $fillable = [
        'clinic_id',
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'userId' => $this->user_id,
            'staffName' => trim(($this->user?->first_name ?? '').' '.($this->user?->last_name ?? '')),
            'dayOfWeek' => $this->day_of_week,
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
        ];
    }
}
