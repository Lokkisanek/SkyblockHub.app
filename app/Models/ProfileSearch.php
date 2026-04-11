<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileSearch extends Model
{
    use HasFactory;

    protected $table = 'profile_searches';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'user_id',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'searched_at' => 'datetime',
        ];
    }
}
