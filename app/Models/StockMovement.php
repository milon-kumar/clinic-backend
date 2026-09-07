<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'clinic_id',
        'service_id',
        'supplier_id',
        'order_id',
        'created_by',
        'type',
        'quantity',
        'unit_cost_pence',
        'reference',
        'notes',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'serviceId' => $this->service_id,
            'serviceName' => $this->service?->name,
            'supplierId' => $this->supplier_id,
            'supplierName' => $this->supplier?->name,
            'orderId' => $this->order_id,
            'type' => $this->type,
            'quantity' => (int) $this->quantity,
            'unitCostPence' => $this->unit_cost_pence,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
