<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'clinic_id',
        'status',
        'subtotal_pence',
        'discount_pence',
        'total_pence',
        'payment_method',
        'stripe_session_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
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
        return $this->hasMany(OrderLine::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(PrepaidPackage::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
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
            'status' => $this->status,
            'subtotalPence' => (int) $this->subtotal_pence,
            'discountPence' => (int) $this->discount_pence,
            'totalPence' => (int) $this->total_pence,
            'paymentMethod' => $this->payment_method,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'invoiceId' => $this->invoice?->id,
            'invoiceNumber' => $this->invoice?->number,
            'lines' => $this->relationLoaded('lines')
                ? $this->lines->map(fn (OrderLine $line) => [
                    'serviceId' => $line->service_id,
                    'name' => $line->service?->name,
                    'quantity' => $line->quantity,
                    'unitPricePence' => $line->unit_price_pence,
                    'lineTotalPence' => $line->quantity * $line->unit_price_pence,
                ])->all()
                : [],
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
