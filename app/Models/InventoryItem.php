<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = [
        'clinic_id',
        'service_id',
        'quantity_on_hand',
        'reorder_level',
        'unit',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function toApi(): array
    {
        $qty = (int) $this->quantity_on_hand;

        return [
            'id' => $this->id,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'serviceId' => $this->service_id,
            'serviceName' => $this->service?->name,
            'sku' => $this->service?->sku,
            'quantityOnHand' => $qty,
            'reorderLevel' => (int) $this->reorder_level,
            'unit' => $this->unit,
            'lowStock' => $qty <= (int) $this->reorder_level,
        ];
    }
}
