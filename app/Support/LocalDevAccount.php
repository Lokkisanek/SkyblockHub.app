<?php

namespace App\Support;

use App\Http\Middleware\DevAutoLogin;

/**
 * Identifiers for the throwaway account created by {@see DevAutoLogin}.
 */
final class LocalDevAccount
{
    /**
     * Discord id sentinel (non-numeric; never collides with real OAuth snowflakes).
     */
    public const DISCORD_ID = '__local_dev_login__';
}
