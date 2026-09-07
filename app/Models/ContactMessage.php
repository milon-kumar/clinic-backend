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
}
