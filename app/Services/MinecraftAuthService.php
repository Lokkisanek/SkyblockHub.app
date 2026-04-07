<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MinecraftAuthService
{
    /**
     * Resolve a Microsoft OAuth refresh token to a Minecraft profile.
     *
     * Flow: MS refresh_token → Xbox Live token → XSTS token → MC token → MC profile
     *
     * @return array{uuid: string, username: string}|null
     */
    public function resolveMinecraftProfile(string $msRefreshToken): ?array
    {
        try {
            $xboxAccessToken = $this->exchangeForXboxToken($msRefreshToken);
            if (!$xboxAccessToken) return null;

            return $this->resolveFromXboxChain($xboxAccessToken);
        } catch (\Throwable $e) {
            Log::warning('MinecraftAuthService: Failed to resolve MC profile', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve MC profile using an access token already scoped to XboxLive.signin.
     */
    public function resolveWithAccessToken(string $xboxAccessToken): ?array
    {
        try {
            return $this->resolveFromXboxChain($xboxAccessToken);
        } catch (\Throwable $e) {
            Log::warning('MinecraftAuthService: Failed to resolve MC profile (access token)', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function resolveFromXboxChain(string $xboxAccessToken): ?array
    {
        Log::info('MinecraftAuthService: Starting Xbox chain');

        $xboxAuth = $this->authenticateXboxLive($xboxAccessToken);
        if (!$xboxAuth) return null;

        Log::info('MinecraftAuthService: Xbox Live auth OK');

        $xstsAuth = $this->getXstsToken($xboxAuth['Token']);
        if (!$xstsAuth) return null;

        Log::info('MinecraftAuthService: XSTS OK');

        $mcToken = $this->loginMinecraft($xstsAuth['uhs'], $xstsAuth['Token']);
        if (!$mcToken) return null;

        Log::info('MinecraftAuthService: MC login OK');

        return $this->getMinecraftProfile($mcToken);
    }

    /**
     * Exchange MS refresh token for an access token with XboxLive.signin scope.
     */
    private function exchangeForXboxToken(string $refreshToken): ?string
    {
        // Xbox Live requires 'consumers' tenant endpoint (personal MS accounts)
        $response = Http::asForm()->post(
            'https://login.microsoftonline.com/consumers/oauth2/v2.0/token',
            [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => 'XboxLive.signin offline_access',
            ]
        );

        if (!$response->ok()) {
            Log::warning('MinecraftAuthService: Xbox token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Authenticate with Xbox Live using a Microsoft access token.
     */
    private function authenticateXboxLive(string $accessToken): ?array
    {
        $response = Http::acceptJson()->post(
            'https://user.auth.xboxlive.com/user/authenticate',
            [
                'Properties' => [
                    'AuthMethod' => 'RPS',
                    'SiteName' => 'user.auth.xboxlive.com',
                    'RpsTicket' => "d={$accessToken}",
                ],
                'RelyingParty' => 'http://auth.xboxlive.com',
                'TokenType' => 'JWT',
            ]
        );

        if (!$response->ok()) {
            Log::warning('MinecraftAuthService: Xbox Live auth failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        $uhs = $data['DisplayClaims']['xui'][0]['uhs'] ?? null;

        return $uhs ? ['Token' => $data['Token'], 'uhs' => $uhs] : null;
    }

    /**
     * Get an XSTS token for Minecraft Services relying party.
     */
    private function getXstsToken(string $xboxLiveToken): ?array
    {
        $response = Http::acceptJson()->post(
            'https://xsts.auth.xboxlive.com/xsts/authorize',
            [
                'Properties' => [
                    'SandboxId' => 'RETAIL',
                    'UserTokens' => [$xboxLiveToken],
                ],
                'RelyingParty' => 'rp://api.minecraftservices.com/',
                'TokenType' => 'JWT',
            ]
        );

        if (!$response->ok()) {
            Log::warning('MinecraftAuthService: XSTS auth failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        $uhs = $data['DisplayClaims']['xui'][0]['uhs'] ?? null;

        return $uhs ? ['Token' => $data['Token'], 'uhs' => $uhs] : null;
    }

    /**
     * Login to Minecraft Services using Xbox XSTS token.
     */
    private function loginMinecraft(string $uhs, string $xstsToken): ?string
    {
        $response = Http::acceptJson()->post(
            'https://api.minecraftservices.com/authentication/login_with_xbox',
            [
                'identityToken' => "XBL3.0 x={$uhs};{$xstsToken}",
            ]
        );

        if (!$response->ok()) {
            Log::warning('MinecraftAuthService: Minecraft login failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Fetch the Minecraft Java Edition profile (UUID + username).
     */
    private function getMinecraftProfile(string $mcAccessToken): ?array
    {
        $response = Http::withToken($mcAccessToken)
            ->acceptJson()
            ->get('https://api.minecraftservices.com/minecraft/profile');

        if ($response->status() === 404) {
            // User does not own Minecraft Java Edition
            return null;
        }

        if (!$response->ok()) {
            Log::warning('MinecraftAuthService: MC profile fetch failed', [
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        $uuid = $data['id'] ?? null;
        $name = $data['name'] ?? null;

        if (!$uuid) return null;

        // Minecraft API returns UUID without dashes — add them
        $formattedUuid = preg_replace(
            '/^(\w{8})(\w{4})(\w{4})(\w{4})(\w{12})$/',
            '$1-$2-$3-$4-$5',
            $uuid
        );

        return [
            'uuid' => $formattedUuid,
            'username' => $name,
        ];
    }
}
