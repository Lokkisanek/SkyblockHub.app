<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slot_index',
        'is_public',
        'grid_columns',
        'grid_rows',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'grid_columns' => 'integer',
            'grid_rows' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('sort_order');
    }
}
