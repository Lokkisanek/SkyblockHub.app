<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_dashboard_id',
        'type',
        'title',
        'x',
        'y',
        'w',
        'h',
        'sort_order',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'w' => 'integer',
            'h' => 'integer',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(UserDashboard::class, 'user_dashboard_id');
    }
}
