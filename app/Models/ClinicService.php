<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicService extends Model
{
    protected $fillable = [
        'clinic_id',
        'service_id',
        'buy_enabled',
        'book_enabled',
        'online_buy_enabled',
        'price_pence',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'buy_enabled' => 'boolean',
            'book_enabled' => 'boolean',
            'online_buy_enabled' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
