<?php

namespace App\Support;

use App\Models\User;

class TestingAdminGate
{
    private const ADMIN_DISCORD_USERNAME = 'lokkisan';
    private const ADMIN_MC_USERNAME = 'Lokkisanecek';

    public static function allows(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $discord = strtolower((string) ($user->discord_username ?? ''));
        $mc = strtolower((string) ($user->minecraft_username ?? ''));

        return $discord === strtolower(self::ADMIN_DISCORD_USERNAME)
            && $mc === strtolower(self::ADMIN_MC_USERNAME);
    }
}
