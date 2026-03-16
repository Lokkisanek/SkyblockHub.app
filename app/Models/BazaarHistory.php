<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BazaarHistory extends Model
{
    use HasFactory;

    protected $table = 'bazaar_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'buy_price',
        'sell_price',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'decimal:4',
            'sell_price' => 'decimal:4',
            'recorded_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BazaarProduct::class, 'product_id', 'product_id');
    }
}
