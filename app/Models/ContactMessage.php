<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'full_name',
        'email',
        'phone',
        'subject',
        'message',
    ];

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
