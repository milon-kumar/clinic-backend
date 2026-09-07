<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'order_id',
        'clinic_id',
        'customer_id',
        'status',
        'subtotal_pence',
        'discount_pence',
        'total_pence',
        'issued_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function toApi(): array
    {
        $order = $this->order;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'orderId' => $this->order_id,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'customerId' => $this->customer_id,
            'customerName' => trim(($this->customer?->first_name ?? '').' '.($this->customer?->last_name ?? '')) ?: $this->customer?->email,
            'customerEmail' => $this->customer?->email,
            'status' => $this->status,
            'subtotalPence' => (int) $this->subtotal_pence,
            'discountPence' => (int) $this->discount_pence,
            'totalPence' => (int) $this->total_pence,
            'issuedAt' => $this->issued_at?->toIso8601String(),
            'notes' => $this->notes,
            'lines' => $order?->relationLoaded('lines')
                ? $order->lines->map(fn (OrderLine $line) => [
                    'name' => $line->service?->name,
                    'quantity' => $line->quantity,
                    'unitPricePence' => $line->unit_price_pence,
                    'lineTotalPence' => $line->quantity * $line->unit_price_pence,
                ])->all()
                : [],
        ];
    }
}
