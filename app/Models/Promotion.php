<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'code',
        'type',
        'rules_json',
        'clinic_id',
        'clinic_ids',
        'requires_login',
        'is_active',
        'starts_at',
        'ends_at',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'rules_json' => 'array',
            'clinic_ids' => 'array',
            'requires_login' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function isValidForClinic(?int $clinicId): bool
    {
        if ($this->clinic_id && $this->clinic_id !== $clinicId) {
            return false;
        }

        $clinicIds = $this->clinic_ids ?? [];
        if (is_string($clinicIds)) {
            $clinicIds = json_decode($clinicIds, true) ?: [];
        }
        if (! empty($clinicIds) && $clinicId && ! in_array($clinicId, $clinicIds, true)) {
            return false;
        }

        $from = $this->starts_at ?? $this->valid_from ?? null;
        $to = $this->ends_at ?? $this->valid_to ?? null;

        if ($from && now()->lt($from)) {
            return false;
        }

        if ($to && now()->gt($to)) {
            return false;
        }

        return true;
    }
}
