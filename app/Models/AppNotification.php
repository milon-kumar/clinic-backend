<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'audience',
        'type',
        'title',
        'body',
        'href',
        'clinic_id',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'audience' => $this->audience,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'href' => $this->href,
            'clinicId' => $this->clinic_id,
            'data' => $this->data ?? [],
            'readAt' => $this->read_at?->toIso8601String(),
            'unread' => $this->read_at === null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
