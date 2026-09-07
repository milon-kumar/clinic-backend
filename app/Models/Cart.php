<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'customer_id',
        'clinic_id',
        'cart_type',
        'promo_code',
        'guest_token',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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

    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'customerName' => trim(($this->customer?->first_name ?? '').' '.($this->customer?->last_name ?? '')) ?: $this->customer?->email,
            'customerEmail' => $this->customer?->email,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'type' => $this->cart_type,
            'promoCode' => $this->promo_code,
            'lines' => $this->relationLoaded('lines')
                ? $this->lines->map(fn (CartLine $line) => [
                    'id' => $line->id,
                    'serviceId' => $line->service_id,
                    'name' => $line->service?->name,
                    'quantity' => $line->quantity,
                    'unitPricePence' => $line->unit_price_pence,
                    'lineTotalPence' => $line->quantity * $line->unit_price_pence,
                ])->all()
                : [],
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
