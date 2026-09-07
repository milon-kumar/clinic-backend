<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicStaff extends Model
{
    protected $table = 'clinic_staff';

    protected $fillable = [
        'clinic_id',
        'user_id',
        'job_title',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

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
            'firstName' => $this->user?->first_name,
            'lastName' => $this->user?->last_name,
            'email' => $this->user?->email,
            'role' => $this->user?->role,
            'jobTitle' => $this->job_title,
            'isActive' => $this->is_active,
        ];
    }
}
