<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    protected $fillable = [
        'code',
        'name',
        'slug',
        'region',
        'address_json',
        'latitude',
        'longitude',
        'phone',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'address_json' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClinicSchedule::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(ClinicClosure::class);
    }

    public function clinicServices(): HasMany
    {
        return $this->hasMany(ClinicService::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function assignedDoctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'clinic_doctor')
            ->withTimestamps();
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(ClinicStaff::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clinic_staff')
            ->withPivot(['job_title', 'is_active'])
            ->withTimestamps();
    }

    public function staffSchedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'region' => $this->region,
            'address' => $this->address_json,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'isActive' => $this->is_active,
            'hours' => $this->hoursApi(),
        ];
    }

    /**
     * Compact location + hours payload nested on doctor profiles.
     *
     * @return array<string, mixed>
     */
    public function toDoctorLocationApi(): array
    {
        $address = $this->address_json ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'region' => $this->region,
            'phone' => $this->phone,
            'address' => $address,
            'city' => $address['city'] ?? null,
            'line1' => $address['line1'] ?? null,
            'postcode' => $address['postcode'] ?? null,
            'hours' => $this->hoursApi(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function hoursApi(): array
    {
        if (! $this->relationLoaded('schedules')) {
            return [];
        }

        return $this->schedules
            ->sortBy('day_of_week')
            ->values()
            ->map(fn (ClinicSchedule $s) => [
                'dayOfWeek' => (int) $s->day_of_week,
                'openTime' => $s->open_time,
                'closeTime' => $s->close_time,
                'slotIntervalMinutes' => $s->slot_interval_minutes,
            ])
            ->all();
    }
}
