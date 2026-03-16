<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BazaarProduct extends Model
{
    use HasFactory;

    protected $table = 'bazaar_products';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'name',
        'category',
        'npc_sell_price',
    ];

    protected function casts(): array
    {
        return [
            'npc_sell_price' => 'decimal:4',
        ];
    }

    public function price(): HasOne
    {
        return $this->hasOne(BazaarPrice::class, 'product_id', 'product_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(BazaarHistory::class, 'product_id', 'product_id');
    }
}
