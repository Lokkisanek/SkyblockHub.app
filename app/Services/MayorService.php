<?php

namespace App\Services;

use App\Models\Mayor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MayorService
{
    private const MAYOR_CACHE_KEY = 'sbh:mayor:current';

    public function __construct(private readonly SkyblockTimeService $skyblockTimeService)
    {
    }

    public function getCurrentMayorData(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::MAYOR_CACHE_KEY);
        }

        return Cache::remember(self::MAYOR_CACHE_KEY, 120, function (): array {
            return $this->refreshMayorData();
        });
    }

    private function refreshMayorData(): array
    {
        $fallback = $this->payloadFromModel(Mayor::query()->latest('last_updated')->first());

        try {
            $response = Http::timeout(8)
                ->retry(2, 250)
                ->get('https://api.hypixel.net/v2/resources/skyblock/election');

            if (! $response->successful()) {
                return $fallback;
            }

            $json = $response->json();
            $payload = $json['election'] ?? $json;
            $currentMayor = $payload['mayor'] ?? $payload['current']['mayor'] ?? null;

            if (! is_array($currentMayor)) {
                return $fallback;
            }

            $name = (string) ($currentMayor['name'] ?? 'Unknown');
            $uuid = isset($currentMayor['uuid']) ? (string) $currentMayor['uuid'] : null;
            $perks = $this->normalizePerks($currentMayor['perks'] ?? []);
            $timeline = $this->extractTimeline($payload) ?? $this->skyblockTimeService->getElectionTimeline();

            Mayor::query()->updateOrCreate(
                ['name' => $name],
                [
                    'uuid' => $uuid,
                    'perks_json' => $perks,
                    'election_raw' => [
                        'source' => 'hypixel',
                        'timeline' => $timeline,
                        'payload' => $payload,
                    ],
                    'last_updated' => now(),
                ]
            );

            return [
                'name' => $name,
                'uuid' => $uuid,
                'perks' => $perks,
                'last_updated' => now()->toIso8601String(),
                'election' => $timeline,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to refresh mayor/election data.', ['error' => $e->getMessage()]);
            return $fallback;
        }
    }

    private function payloadFromModel(?Mayor $mayor): array
    {
        if (! $mayor) {
            return [
                'name' => 'Unknown',
                'uuid' => null,
                'perks' => [],
                'last_updated' => null,
                'election' => $this->skyblockTimeService->getElectionTimeline(),
            ];
        }

        return [
            'name' => $mayor->name,
            'uuid' => $mayor->uuid,
            'perks' => $this->normalizePerks($mayor->perks_json ?? []),
            'last_updated' => optional($mayor->last_updated)?->toIso8601String(),
            'election' => data_get($mayor->election_raw, 'timeline', $this->skyblockTimeService->getElectionTimeline()),
        ];
    }

    /**
     * @param mixed $perks
     * @return array<int, array{name:string,description:?string}>
     */
    private function normalizePerks(mixed $perks): array
    {
        if (! is_array($perks)) {
            return [];
        }

        $result = [];

        foreach ($perks as $perk) {
            if (is_string($perk)) {
                $result[] = [
                    'name' => $perk,
                    'description' => null,
                ];
                continue;
            }

            if (is_array($perk)) {
                $name = (string) ($perk['name'] ?? 'Unknown Perk');
                $description = isset($perk['description']) ? (string) $perk['description'] : null;

                $result[] = [
                    'name' => $name,
                    'description' => $description,
                ];
            }
        }

        return $result;
    }

    /**
     * Try to parse explicit timeline values from API payload.
     * Falls back to null when unavailable.
     */
    private function extractTimeline(array $payload): ?array
    {
        $startUnix = $this->toUnix(data_get($payload, 'current.start'));
        $endUnix = $this->toUnix(data_get($payload, 'current.end'));
        $officeUnix = $this->toUnix(data_get($payload, 'current.mayor_start'));

        if (! $startUnix || ! $endUnix) {
            return null;
        }

        if (! $officeUnix) {
            $officeUnix = $endUnix + SkyblockTimeService::DAY_SECONDS;
        }

        $now = time();
        $phase = 'upcoming';
        $secondsRemaining = max(0, $startUnix - $now);

        if ($now >= $startUnix && $now < $endUnix) {
            $phase = 'election_live';
            $secondsRemaining = max(0, $endUnix - $now);
        } elseif ($now >= $endUnix && $now < $officeUnix) {
            $phase = 'mayor_transition';
            $secondsRemaining = max(0, $officeUnix - $now);
        }

        return [
            'start_unix' => $startUnix,
            'end_unix' => $endUnix,
            'office_unix' => $officeUnix,
            'phase' => $phase,
            'seconds_remaining' => $secondsRemaining,
        ];
    }

    private function toUnix(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $num = (int) $value;
        if ($num > 9_999_999_999) {
            $num = (int) floor($num / 1000);
        }

        return $num > 0 ? $num : null;
    }
}
