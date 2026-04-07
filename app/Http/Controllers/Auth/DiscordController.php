<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $discordUser = Socialite::driver('discord')->user();

        $user = $this->resolveUser($discordUser);

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }

    private function resolveUser($discordUser): User
    {
        $discordId = $discordUser->getId();
        $email = $discordUser->getEmail();

        // 1. Check by Discord ID — returning user
        $userByDiscord = User::where('discord_id', $discordId)->first();
        if ($userByDiscord) {
            $userByDiscord->update([
                'discord_username' => $discordUser->getNickname(),
                'discord_avatar' => $discordUser->getAvatar(),
                'email' => $userByDiscord->email ?? $email,
            ]);
            return $userByDiscord;
        }

        // 2. Check by email — merge with existing MS/MC account
        $userByEmail = $email ? User::where('email', $email)->first() : null;
        if ($userByEmail) {
            $userByEmail->update([
                'discord_id' => $discordId,
                'discord_username' => $discordUser->getNickname(),
                'discord_avatar' => $discordUser->getAvatar(),
            ]);
            return $userByEmail;
        }

        // 3. New user — Discord-only account
        return User::create([
            'discord_id' => $discordId,
            'name' => $discordUser->getName() ?? $discordUser->getNickname(),
            'email' => $email,
            'discord_username' => $discordUser->getNickname(),
            'discord_avatar' => $discordUser->getAvatar(),
        ]);
    }

    /**
     * Link Discord to an existing authenticated account (e.g. MS user adding Discord).
     */
    public function redirectLink(): RedirectResponse
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->redirectUrl(config('services.discord.redirect_link'))
            ->redirect();
    }

    public function callbackLink(Request $request): RedirectResponse
    {
        $discordUser = Socialite::driver('discord')
            ->redirectUrl(config('services.discord.redirect_link'))
            ->user();

        $user = $request->user();

        // Check if this Discord ID is already linked to a different user
        $existingDiscord = User::where('discord_id', $discordUser->getId())
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingDiscord) {
            return redirect()->route('profile.edit')
                ->with('status', 'Tento Discord účet je již propojen s jiným uživatelem.');
        }

        $user->update([
            'discord_id' => $discordUser->getId(),
            'discord_username' => $discordUser->getNickname(),
            'discord_avatar' => $discordUser->getAvatar(),
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'Discord úspěšně propojen!');
    }
}
