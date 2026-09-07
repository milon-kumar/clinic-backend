<?php

namespace App\Models;

use App\Support\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'address',
        'role',
        'clinic_id',
        'is_verified',
        'selected_clinic_id',
        'date_of_birth',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function selectedClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'selected_clinic_id');
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(ClinicStaff::class);
    }

    public function staffSchedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function assignedClinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_staff')
            ->withPivot(['job_title', 'is_active'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return Roles::isSuperAdmin($this->role);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleClinicIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Clinic::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ids = $this->staffAssignments()
            ->where('is_active', true)
            ->pluck('clinic_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($this->clinic_id) {
            $ids[] = (int) $this->clinic_id;
        }

        return array_values(array_unique($ids));
    }

    public function canAccessClinic(int $clinicId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($clinicId, $this->accessibleClinicIds(), true);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function prepaidPackages(): HasMany
    {
        return $this->hasMany(PrepaidPackage::class, 'customer_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'role' => $this->role,
            'isSuperAdmin' => $this->isSuperAdmin(),
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'isVerified' => (bool) $this->is_verified,
            'selectedClinicId' => $this->selected_clinic_id,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
