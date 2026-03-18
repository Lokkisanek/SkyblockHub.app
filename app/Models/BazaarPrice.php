<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BazaarPrice extends Model
{
    use HasFactory;

    protected $table = 'bazaar_prices';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = null;

    protected $fillable = [
        'product_id',
        'buy_price',
        'sell_price',
        'buy_volume',
        'sell_volume',
        'buy_moving_week',
        'sell_moving_week',
        'buy_orders',
        'sell_orders',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'decimal:4',
            'sell_price' => 'decimal:4',
            'buy_volume' => 'integer',
            'sell_volume' => 'integer',
            'buy_moving_week' => 'decimal:4',
            'sell_moving_week' => 'decimal:4',
            'buy_orders' => 'integer',
            'sell_orders' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BazaarProduct::class, 'product_id', 'product_id');
    }
}
