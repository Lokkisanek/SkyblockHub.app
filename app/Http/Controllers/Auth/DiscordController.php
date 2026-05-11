<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $this->storeIntendedRedirect($request);
        

        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $discordUser = $this->fetchDiscordUser(
            request(),
            config('services.discord.redirect')
        );

        $user = $this->resolveUser($discordUser);

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }

    private function resolveUser(array $discordUser): User
    {
        $discordId = $discordUser['id'];
        $email = $discordUser['email'] ?? null;
        $username = $discordUser['username'] ?? $discordUser['global_name'] ?? $discordId;
        $avatar = $this->discordAvatarUrl($discordUser['id'], $discordUser['avatar'] ?? null);

        // 1. Check by Discord ID — returning user
        $userByDiscord = User::where('discord_id', $discordId)->first();
        if ($userByDiscord) {
            $userByDiscord->update([
                'discord_username' => $username,
                'discord_avatar' => $avatar,
                'email' => $userByDiscord->email ?? $email,
            ]);
            return $userByDiscord;
        }

        // 2. Check by email — merge with existing MS/MC account
        $userByEmail = $email ? User::where('email', $email)->first() : null;
        if ($userByEmail) {
            $userByEmail->update([
                'discord_id' => $discordId,
                'discord_username' => $username,
                'discord_avatar' => $avatar,
            ]);
            return $userByEmail;
        }

        // 3. New user — Discord-only account
        return User::create([
            'discord_id' => $discordId,
            'name' => $discordUser['global_name'] ?? $username,
            'email' => $email,
            'discord_username' => $username,
            'discord_avatar' => $avatar,
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
        $discordUser = $this->fetchDiscordUser(
            $request,
            config('services.discord.redirect_link')
        );

        $user = $request->user();
        $username = $discordUser['username'] ?? $discordUser['global_name'] ?? $discordUser['id'];
        $avatar = $this->discordAvatarUrl($discordUser['id'], $discordUser['avatar'] ?? null);

        // Check if this Discord ID is already linked to a different user
        $existingDiscord = User::where('discord_id', $discordUser['id'])
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingDiscord) {
            return redirect()->route('profile.edit')
                ->with('status', 'Tento Discord účet je již propojen s jiným uživatelem.');
        }

        $user->update([
            'discord_id' => $discordUser['id'],
            'discord_username' => $username,
            'discord_avatar' => $avatar,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'Discord úspěšně propojen!');
    }

    private function fetchDiscordUser(Request $request, string $redirectUri): array
    {
        $code = (string) $request->query('code', '');

        if ($code === '') {
            abort(400, 'Chybí OAuth code.');
        }

        $tokenResponse = Http::asForm()
            ->post('https://discord.com/api/oauth2/token', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ])
            ->throw()
            ->json();

        return Http::withToken($tokenResponse['access_token'])
            ->get('https://discord.com/api/users/@me')
            ->throw()
            ->json();
    }

    private function discordAvatarUrl(string $discordId, ?string $avatarHash): ?string
    {
        if (! $avatarHash) {
            return null;
        }

        return "https://cdn.discordapp.com/avatars/{$discordId}/{$avatarHash}.png?size=128";
    }

    private function storeIntendedRedirect(Request $request): void
    {
        $redirect = (string) $request->query('redirect', '');

        if ($this->isSafeLocalRedirect($redirect)) {
            $request->session()->put('url.intended', $redirect);
        }
    }

    private function isSafeLocalRedirect(string $redirect): bool
    {
        return $redirect !== ''
            && str_starts_with($redirect, '/')
            && ! str_starts_with($redirect, '//')
            && ! str_contains($redirect, "\n")
            && ! str_contains($redirect, "\r");
    }
}
