<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    use HasFactory;

    protected $table = 'recipes';

    public $timestamps = false;

    protected $fillable = [
        'output_product_id',
        'output_quantity',
        'category',
        'ingredients_json',
    ];

    protected function casts(): array
    {
        return [
            'output_quantity' => 'integer',
            'ingredients_json' => 'array',
        ];
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(BazaarProduct::class, 'output_product_id', 'product_id');
    }
}
