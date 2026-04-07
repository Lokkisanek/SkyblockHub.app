<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HypixelLinkingService
{
    public function __construct(
        private HypixelApiProxy $hypixel,
    ) {}

    /**
     * Resolve a Minecraft username to UUID via Mojang API.
     *
     * @return array{uuid: string, username: string}|null
     */
    public function resolveUuid(string $username): ?array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->get("https://api.mojang.com/users/profiles/minecraft/{$username}");

            if (!$response->ok()) return null;

            $data = $response->json();
            $uuid = $data['id'] ?? null;
            $name = $data['name'] ?? null;

            if (!$uuid) return null;

            $formattedUuid = preg_replace(
                '/^(\w{8})(\w{4})(\w{4})(\w{4})(\w{12})$/',
                '$1-$2-$3-$4-$5',
                $uuid
            );

            return ['uuid' => $formattedUuid, 'username' => $name];
        } catch (\Throwable $e) {
            Log::warning('HypixelLinkingService: Mojang UUID resolve failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if a player's Hypixel Discord social link matches the given Discord username.
     */
    public function verifyDiscordLink(string $mcUuid, string $discordUsername): bool
    {
        $cleanUuid = str_replace('-', '', $mcUuid);

        $player = $this->getHypixelPlayer($cleanUuid);
        if (!$player) return false;

        $hypixelDiscord = $player['socialMedia']['links']['DISCORD'] ?? null;
        if (!$hypixelDiscord) return false;

        return strtolower(trim($hypixelDiscord)) === strtolower(trim($discordUsername));
    }

    /**
     * Get raw Hypixel player data by UUID (without dashes).
     */
    public function getHypixelPlayer(string $cleanUuid): ?array
    {
        return $this->hypixel->getPlayer($cleanUuid);
    }
}
