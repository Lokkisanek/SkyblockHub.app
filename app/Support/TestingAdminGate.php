<?php

namespace App\Support;

use App\Models\User;

class TestingAdminGate
{
    private const ADMIN_EMAIL = 'odehnalm.08@spst.eu';
    private const ADMIN_MC_USERNAME = 'Matyode_1590';

    public static function allows(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $email = strtolower((string) $user->email);
        $minecraftUsername = strtolower((string) ($user->minecraft_username ?? ''));
        $emailMatch = $email === strtolower(self::ADMIN_EMAIL);
        $minecraftMatch = $minecraftUsername !== ''
            && $minecraftUsername === strtolower(self::ADMIN_MC_USERNAME);

        // Allow by either identifier so admins are not blocked when Minecraft username
        // has not yet been linked on the account profile.
        return $emailMatch || $minecraftMatch;
    }
}
