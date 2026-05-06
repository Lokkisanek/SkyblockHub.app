<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    use HasFactory;

    protected $table = 'user_activity';

    protected $fillable = [
        'user_id',
        'session_minutes_played',
        'profile_views_today',
        'tracked_date',
    ];

    protected function casts(): array
    {
        return [
            'session_minutes_played' => 'integer',
            'profile_views_today' => 'integer',
            'tracked_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
