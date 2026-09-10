<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackage extends Model
{
    protected $fillable = [
        'service_id',
        'title',
        'sessions',
        'discount_percent',
        'price_pence',
    ];

    protected function casts(): array
    {
        return [
            'sessions' => 'integer',
            'discount_percent' => 'integer',
            'price_pence' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function toApi(): array
    {
        $sessions = max(1, (int) $this->sessions);
        $discount = min(100, max(0, (int) $this->discount_percent));

        return [
            'id' => $this->id,
            'title' => $this->title,
            'sessions' => $sessions,
            'quantity' => $sessions,
            'discountPercent' => $discount,
            'pricePence' => $this->price_pence,
            'price' => $this->price_pence / 100,
        ];
    }
}
