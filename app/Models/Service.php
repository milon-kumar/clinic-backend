<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'sku',
        'slug',
        'name',
        'category',
        'treatment_type',
        'description',
        'duration_minutes',
        'base_price_pence',
        'appointment_amount_pence',
        'images',
        'supports_buy',
        'supports_book',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'supports_buy' => 'boolean',
            'supports_book' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(ServiceBenefit::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ServiceFaq::class);
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(ServicePrerequisite::class);
    }

    public function clinicServices(): HasMany
    {
        return $this->hasMany(ClinicService::class);
    }

    public function appointmentAmountPence(): int
    {
        return max(0, (int) $this->appointment_amount_pence);
    }

    public function isFreeAppointment(): bool
    {
        return $this->appointmentAmountPence() === 0;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'name' => $this->name,
            'title' => $this->name,
            'category' => $this->category,
            'treatmentType' => $this->treatment_type,
            'description' => $this->description,
            'durationMinutes' => $this->duration_minutes,
            'basePricePence' => $this->base_price_pence,
            'price' => $this->base_price_pence / 100,
            'appointmentAmountPence' => $this->appointmentAmountPence(),
            'appointmentAmount' => $this->appointmentAmountPence() / 100,
            'images' => $this->images ?? [],
            'supportsBuy' => $this->supports_buy,
            'supportsBook' => $this->supports_book,
            'isActive' => $this->is_active,
            'isFeatured' => $this->is_featured,
            'packages' => $this->relationLoaded('packages')
                ? $this->packages->map(fn (ServicePackage $p) => $p->toApi())->all()
                : [],
            'benefits' => $this->relationLoaded('benefits')
                ? $this->benefits->map(fn (ServiceBenefit $b) => $b->toApi())->all()
                : [],
            'faqs' => $this->relationLoaded('faqs')
                ? $this->faqs->map(fn (ServiceFaq $f) => $f->toApi())->all()
                : [],
            'clinicIds' => $this->relationLoaded('clinicServices')
                ? $this->clinicServices->pluck('clinic_id')->map(fn ($id) => (int) $id)->values()->all()
                : [],
            'clinics' => $this->relationLoaded('clinicServices')
                ? $this->clinicServices->map(fn (ClinicService $row) => [
                    'id' => $row->clinic_id,
                    'name' => $row->clinic?->name,
                    'buyEnabled' => (bool) $row->buy_enabled,
                    'bookEnabled' => (bool) $row->book_enabled,
                    'onlineBuyEnabled' => (bool) $row->online_buy_enabled,
                ])->values()->all()
                : [],
        ];
    }
}
