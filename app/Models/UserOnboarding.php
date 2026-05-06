<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'completed_steps',
        'dismissed_at',
        'completed_at',
        'copy_variant',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'dismissed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
