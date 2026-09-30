<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePreQuestion extends Model
{
    protected $fillable = [
        'service_id',
        'prompt',
        'answer_type',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'answerType' => $this->answer_type,
            'required' => (bool) $this->is_required,
        ];
    }
}
