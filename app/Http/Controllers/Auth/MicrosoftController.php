<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MinecraftAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $this->storeIntendedRedirect($request);

        return Socialite::driver('microsoft')
            ->scopes(['User.Read', 'XboxLive.signin', 'offline_access'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(MinecraftAuthService $mcService): RedirectResponse
    {
        $microsoftUser = Socialite::driver('microsoft')->user();

        Log::info('MicrosoftController: OAuth callback', [
            'ms_id' => $microsoftUser->getId(),
        ]);

        // Resolve Minecraft profile via Xbox Live chain
        $mcProfile = null;
        $refreshToken = $microsoftUser->refreshToken;
        $accessToken = $microsoftUser->token;

        if ($refreshToken) {
            $mcProfile = $mcService->resolveMinecraftProfile($refreshToken);
        }
        if (!$mcProfile && $accessToken) {
            $mcProfile = $mcService->resolveWithAccessToken($accessToken);
        }

        if ($mcProfile) {
            Log::info('MicrosoftController: MC profile resolved', [
                'uuid' => $mcProfile['uuid'],
                'username' => $mcProfile['username'],
            ]);
        } else {
            Log::info('MicrosoftController: Could not resolve MC profile');
        }

        $user = $this->resolveUser($microsoftUser, $mcProfile);

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }

    private function resolveUser($microsoftUser, ?array $mcProfile): User
    {
        $microsoftId = $microsoftUser->getId();
        $email = $microsoftUser->getEmail();

        // Priority: MS ID → MC UUID → email
        $userByMs = User::where('microsoft_id', $microsoftId)->first();
        $userByMc = $mcProfile ? User::where('minecraft_uuid', $mcProfile['uuid'])->first() : null;
        $userByEmail = $email ? User::where('email', $email)->whereNull('microsoft_id')->first() : null;

        $mcData = $mcProfile ? [
            'minecraft_uuid' => $mcProfile['uuid'],
            'minecraft_username' => $mcProfile['username'],
            'is_mc_linked' => true,
        ] : [];

        if ($userByMs) {
            $updateData = [
                'name' => $microsoftUser->getName() ?? $microsoftUser->getNickname(),
                'email' => $email,
            ];

            // If MC profile resolved and user doesn't have it linked yet, or username changed
            if ($mcProfile) {
                $updateData = array_merge($updateData, $mcData);
            }

            // Handle case: MS user exists but MC UUID belongs to a different user
            if ($userByMc && $userByMc->id !== $userByMs->id) {
                // Merge: move MS data to MC user (MC UUID is identity anchor)
                $userByMc->update(array_merge($updateData, [
                    'microsoft_id' => $microsoftId,
                ]));

                if ($userByMs->discord_id && !$userByMc->discord_id) {
                    $userByMc->update([
                        'discord_id' => $userByMs->discord_id,
                        'discord_username' => $userByMs->discord_username,
                        'discord_avatar' => $userByMs->discord_avatar,
                    ]);
                }

                $userByMs->update(['microsoft_id' => null]);
                $userByMs->delete();

                return $userByMc;
            }

            $userByMs->update($updateData);
            return $userByMs;
        }

        if ($userByMc) {
            $userByMc->update([
                'microsoft_id' => $microsoftId,
                'name' => $microsoftUser->getName() ?? $microsoftUser->getNickname(),
                'email' => $email,
            ]);
            return $userByMc;
        }

        if ($userByEmail) {
            $userByEmail->update(array_merge([
                'microsoft_id' => $microsoftId,
                'name' => $microsoftUser->getName() ?? $microsoftUser->getNickname(),
                'email' => $email,
            ], $mcData));
            return $userByEmail;
        }

        return User::create(array_merge([
            'microsoft_id' => $microsoftId,
            'name' => $microsoftUser->getName() ?? $microsoftUser->getNickname(),
            'email' => $email,
        ], $mcData));
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
