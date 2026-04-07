<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'discord_id',
        'discord_username',
        'discord_avatar',
        'microsoft_id',
        'minecraft_uuid',
        'minecraft_username',
        'is_mc_linked',
        'karma_score',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_mc_linked' => 'boolean',
            'karma_score' => 'integer',
        ];
    }

    public function dungeonParties(): HasMany
    {
        return $this->hasMany(DungeonParty::class);
    }

    public function karmaVotesGiven(): HasMany
    {
        return $this->hasMany(KarmaVote::class, 'voter_id');
    }

    public function karmaVotesReceived(): HasMany
    {
        return $this->hasMany(KarmaVote::class, 'target_id');
    }
}
