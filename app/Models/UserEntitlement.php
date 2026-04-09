<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dashboard_slots_unlocked',
        'status',
        'provider',
        'stripe_customer_id',
        'stripe_subscription_id',
        'current_period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'dashboard_slots_unlocked' => 'integer',
            'current_period_ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
