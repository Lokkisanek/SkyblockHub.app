<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunnelEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'user_id',
        'session_id',
        'path',
        'referrer',
        'properties',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'properties' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
