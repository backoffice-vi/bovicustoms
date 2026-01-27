<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitlistSignup extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'interested_features',
        'comments',
        'source',
        'ip_address',
        'user_agent',
        'notified',
    ];

    protected $casts = [
        'interested_features' => 'array',
        'notified' => 'boolean',
    ];
}
