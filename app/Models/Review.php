<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'user_id',
        'clinic_id',
        'service_id',
        'appointment_id',
        'rating',
        'title',
        'body',
        'status',
        'admin_reply',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function toApi(string $audience = 'public'): array
    {
        $staff = $audience === 'staff';
        $owner = $audience === 'owner' || $staff;

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $owner ? $this->status : self::STATUS_PUBLISHED,
            'serviceId' => $this->service_id,
            'serviceName' => $this->service?->name,
            'clinicId' => $this->clinic_id,
            'clinicName' => $this->clinic?->name,
            'appointmentId' => $this->appointment_id,
            'customerName' => $staff
                ? ($this->user?->name ?: $this->user?->email)
                : $this->publicAuthorName(),
            'customerEmail' => $staff ? $this->user?->email : null,
            'adminReply' => $this->admin_reply,
            'repliedAt' => $this->replied_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    public function publicAuthorName(): string
    {
        $first = trim((string) ($this->user?->first_name ?: explode(' ', (string) $this->user?->name)[0] ?: 'Client'));
        $last = trim((string) ($this->user?->last_name ?: ''));
        $initial = $last !== '' ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : '';

        return $first.$initial;
    }
}
