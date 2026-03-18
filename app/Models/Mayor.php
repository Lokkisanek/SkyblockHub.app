<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mayor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uuid',
        'perks_json',
        'election_raw',
        'last_updated',
    ];

    protected $casts = [
        'perks_json' => 'array',
        'election_raw' => 'array',
        'last_updated' => 'datetime',
    ];
}
