<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrerequisite extends Model
{
    protected $fillable = [
        'service_id',
        'prerequisite_service_id',
        'rule',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function prerequisiteService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'prerequisite_service_id');
    }
}
