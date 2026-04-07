<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HypixelLinkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LinkMinecraftController extends Controller
{
    public function verify(Request $request, HypixelLinkingService $service): RedirectResponse
    {
        $request->validate([
            'minecraft_username' => ['required', 'string', 'min:3', 'max:16', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        $user = $request->user();

        if ($user->is_mc_linked) {
            return back()->withErrors(['minecraft_username' => 'Minecraft účet je již propojen.']);
        }

        if (!$user->discord_username) {
            return back()->withErrors(['minecraft_username' => 'Musíte být přihlášeni přes Discord pro tuto metodu ověření.']);
        }

        // 1. Resolve MC username to UUID via Mojang
        $mcProfile = $service->resolveUuid($request->minecraft_username);

        if (!$mcProfile) {
            return back()->withErrors(['minecraft_username' => 'Minecraft hráč nenalezen.']);
        }

        // 2. Check if MC UUID is already taken by another user
        $existingMcUser = User::where('minecraft_uuid', $mcProfile['uuid'])
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingMcUser) {
            // Merge: move Discord data to existing MC account
            $existingMcUser->update([
                'discord_id' => $user->discord_id,
                'discord_username' => $user->discord_username,
                'discord_avatar' => $user->discord_avatar,
            ]);

            Auth::logout();
            $user->delete();
            Auth::login($existingMcUser, remember: true);

            return redirect()->route('profile.edit')
                ->with('status', 'Účty sloučeny! Discord byl propojen s tvým existujícím MC účtem.');
        }

        // 3. Verify Discord social on Hypixel
        if (!$service->verifyDiscordLink($mcProfile['uuid'], $user->discord_username)) {
            return back()->withErrors([
                'minecraft_username' => 'Ověření selhalo. Zkontroluj, že tvůj Discord na Hypixelu odpovídá: ' . $user->discord_username,
            ]);
        }

        // 4. Link MC to current account
        $user->update([
            'minecraft_uuid' => $mcProfile['uuid'],
            'minecraft_username' => $mcProfile['username'],
            'is_mc_linked' => true,
        ]);

        return back()->with('status', 'Minecraft účet úspěšně propojen!');
    }

    /**
     * Link MC account by username with Hypixel Discord verification.
     * Requires discord_username to be set on the user account.
     */
    public function linkDirect(Request $request, HypixelLinkingService $service): RedirectResponse
    {
        $request->validate([
            'minecraft_username' => ['required', 'string', 'min:3', 'max:16', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        $user = $request->user();

        if ($user->is_mc_linked) {
            return back()->withErrors(['minecraft_username' => 'Minecraft účet je již propojen.']);
        }

        if (!$user->discord_username) {
            return back()->withErrors(['minecraft_username' => 'Nejdřív propoj svůj Discord účet.']);
        }

        $mcProfile = $service->resolveUuid($request->minecraft_username);

        if (!$mcProfile) {
            return back()->withErrors(['minecraft_username' => 'Minecraft hráč nenalezen.']);
        }

        // Verify Discord social on Hypixel matches the user's Discord
        if (!$service->verifyDiscordLink($mcProfile['uuid'], $user->discord_username)) {
            return back()->withErrors([
                'minecraft_username' => 'Ověření selhalo. Na Hypixel profilu hráče ' . $mcProfile['username'] . ' musí být v Social Media → Discord nastaveno: ' . $user->discord_username,
            ]);
        }

        // Check for UUID collision — merge accounts if needed
        $existingMcUser = User::where('minecraft_uuid', $mcProfile['uuid'])
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingMcUser) {
            if (!$existingMcUser->discord_id && $user->discord_id) {
                $existingMcUser->update([
                    'discord_id' => $user->discord_id,
                    'discord_username' => $user->discord_username,
                    'discord_avatar' => $user->discord_avatar,
                ]);
            }

            Auth::logout();
            $user->delete();
            Auth::login($existingMcUser, remember: true);

            return redirect()->route('profile.edit')
                ->with('status', 'Účty sloučeny! Minecraft účet propojen.');
        }

        $user->update([
            'minecraft_uuid' => $mcProfile['uuid'],
            'minecraft_username' => $mcProfile['username'],
            'is_mc_linked' => true,
        ]);

        return back()->with('status', 'Minecraft účet úspěšně propojen!');
    }

    /**
     * Unlink the current Minecraft account so the user can re-link a different one.
     */
    public function unlink(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->is_mc_linked) {
            return back();
        }

        $user->update([
            'minecraft_uuid' => null,
            'minecraft_username' => null,
            'is_mc_linked' => false,
        ]);

        session()->forget('mc_verify');

        return back()->with('status', 'Minecraft účet odpojen. Můžeš propojit jiný.');
    }
}
