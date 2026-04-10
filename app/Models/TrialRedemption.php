<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrialRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_id',
        'first_user_id',
        'tier',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_user_id' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }
}
