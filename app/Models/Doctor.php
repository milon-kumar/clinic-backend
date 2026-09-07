<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Doctor extends Model
{
    protected $fillable = [
        'name',
        'kicker',
        'name_accent',
        'tagline',
        'specialty',
        'professional_title',
        'bio',
        'image',
        'cv',
        'credentials',
        'expertise_kicker',
        'expertise_heading',
        'expertise_tags',
        'expertise',
        'education',
        'promises',
        'cta_title',
        'cta_copy',
        'clinic_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credentials' => 'array',
            'expertise' => 'array',
            'education' => 'array',
            'promises' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_doctor')
            ->withTimestamps();
    }

    /**
     * @param  array<int, int|string>  $clinicIds
     */
    public function syncClinics(array $clinicIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $clinicIds)));
        $this->clinics()->sync($ids);
        $this->update(['clinic_id' => $ids[0] ?? null]);
        $this->unsetRelation('clinics');
        $this->unsetRelation('clinic');
    }

    public function assignClinic(int $clinicId): void
    {
        $this->clinics()->syncWithoutDetaching([$clinicId]);

        if (! $this->clinic_id) {
            $this->update(['clinic_id' => $clinicId]);
        }

        $this->unsetRelation('clinics');
        $this->unsetRelation('clinic');
    }

    public function unassignClinic(int $clinicId): void
    {
        $this->clinics()->detach($clinicId);

        if ((int) $this->clinic_id === $clinicId) {
            $this->update(['clinic_id' => $this->clinics()->first()?->id]);
        }

        $this->unsetRelation('clinics');
        $this->unsetRelation('clinic');
    }

    public function toApi(): array
    {
        $clinics = $this->apiClinics();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'kicker' => $this->kicker,
            'nameAccent' => $this->name_accent,
            'tagline' => $this->tagline,
            'specialty' => $this->specialty,
            'professionalTitle' => $this->professional_title,
            'bio' => $this->bio,
            'image' => $this->image,
            'cv' => $this->cv,
            'credentials' => array_values($this->credentials ?? []),
            'expertiseKicker' => $this->expertise_kicker,
            'expertiseHeading' => $this->expertise_heading,
            'expertiseTags' => $this->expertise_tags,
            'expertise' => array_values($this->expertise ?? []),
            'education' => array_values($this->education ?? []),
            'promises' => array_values($this->promises ?? []),
            'ctaTitle' => $this->cta_title,
            'ctaCopy' => $this->cta_copy,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name ?? $clinics->first()?->name,
            'clinicIds' => $clinics->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'clinics' => $clinics->map(fn (Clinic $clinic) => $clinic->toDoctorLocationApi())->values()->all(),
            'isActive' => $this->is_active,
        ];
    }

    /**
     * @return Collection<int, Clinic>
     */
    private function apiClinics(): Collection
    {
        if ($this->relationLoaded('clinics')) {
            return $this->clinics->sortBy('name')->values();
        }

        if ($this->relationLoaded('clinic') && $this->clinic) {
            return collect([$this->clinic]);
        }

        return collect();
    }
}
