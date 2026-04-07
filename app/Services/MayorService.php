<?php

namespace App\Services;

use App\Models\Mayor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MayorService
{
    private const MAYOR_CACHE_KEY = 'sbh:mayor:current';

    /**
     * @return array<int, array{name:string,summary:?string,perks:array<int, array{name:string,description:?string}>}>
     */
    private function mayorCatalog(): array
    {
        return (array) config('mayors.catalog', []);
    }

    public function __construct(
        private readonly SkyblockTimeService $skyblockTimeService,
        private readonly HypixelApiProxy $hypixelApi,
    ) {
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
            $json = $this->hypixelApi->getElection();

            if ($json === null) {
                return $fallback;
            }

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

            // Store election candidates so we build a full mayor roster
            $this->storeElectionCandidates($payload);

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
    /** Strip Minecraft color/formatting codes (§x) from a string. */
    private function stripMinecraftCodes(string $text): string
    {
        return preg_replace('/§[0-9a-fk-or]/i', '', $text);
    }

    private function normalizePerks(mixed $perks): array
    {
        if (! is_array($perks)) {
            return [];
        }

        $result = [];

        foreach ($perks as $perk) {
            if (is_string($perk)) {
                $result[] = [
                    'name' => $this->stripMinecraftCodes($perk),
                    'description' => null,
                ];
                continue;
            }

            if (is_array($perk)) {
                $name = $this->stripMinecraftCodes((string) ($perk['name'] ?? 'Unknown Perk'));
                $description = isset($perk['description']) ? $this->stripMinecraftCodes((string) $perk['description']) : null;

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

    private function storeElectionCandidates(array $payload): void
    {
        $candidates = data_get($payload, 'current.candidates', []);

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || empty($candidate['name'])) {
                continue;
            }

            $candidateName = (string) $candidate['name'];
            $existing = Mayor::query()->where('name', $candidateName)->first();

            // Don't overwrite the current mayor's fresh data
            if ($existing && $existing->last_updated && $existing->last_updated->gt(now()->subMinutes(5))) {
                continue;
            }

            $perks = $this->normalizePerks($candidate['perks'] ?? []);

            Mayor::query()->updateOrCreate(
                ['name' => $candidateName],
                [
                    'uuid' => isset($candidate['uuid']) ? (string) $candidate['uuid'] : ($existing->uuid ?? null),
                    'perks_json' => $perks,
                    'last_updated' => $existing?->last_updated ?? now(),
                ]
            );
        }
    }

    public function getAllMayors(): array
    {
        // Ensure fresh data
        $this->getCurrentMayorData();

        $currentMayorData = $this->getCurrentMayorData();
        $currentName = $currentMayorData['name'] ?? null;

        $databaseMayors = Mayor::query()
            ->orderByRaw("CASE WHEN name = ? THEN 0 ELSE 1 END", [$currentName])
            ->orderBy('name')
            ->get()
            ->map(function (Mayor $mayor) use ($currentName) {
                return [
                    'name' => $mayor->name,
                    'uuid' => $mayor->uuid,
                    'perks' => $this->normalizePerks($mayor->perks_json ?? []),
                    'is_active' => $mayor->name === $currentName,
                    'last_updated' => $mayor->last_updated?->toIso8601String(),
                    'last_elected' => $mayor->last_updated?->diffForHumans(),
                    'summary' => null,
                    'skin_name' => null,
                ];
            })
            ->keyBy('name');

        $merged = collect($this->mayorCatalog())
            ->map(function (array $catalogMayor) use ($databaseMayors, $currentName) {
                $name = (string) ($catalogMayor['name'] ?? 'Unknown');
                $existing = $databaseMayors->get($name);
                $catalogPerks = $this->normalizePerks($catalogMayor['perks'] ?? []);

                return [
                    'name'       => $name,
                    'label'      => $catalogMayor['label'] ?? $name,
                    'category'   => $catalogMayor['category'] ?? 'regular',
                    'uuid'       => $existing['uuid'] ?? null,
                    'perks'      => ! empty($existing['perks']) ? $existing['perks'] : $catalogPerks,
                    'is_active'  => ($existing['is_active'] ?? false) || $name === $currentName,
                    'last_updated' => $existing['last_updated'] ?? null,
                    'last_elected' => $existing['last_elected'] ?? null,
                    'summary'    => $catalogMayor['summary'] ?? null,
                    'skin_name'  => $catalogMayor['skin_name'] ?? null,
                    'skin_alt'   => $catalogMayor['skin_alt'] ?? null,
                ];
            })
            ->keyBy('name');

        foreach ($databaseMayors as $name => $databaseMayor) {
            if (! $merged->has($name)) {
                $merged->put($name, $databaseMayor);
            }
        }

        return $merged
            ->sortBy(fn (array $mayor) => [
                $mayor['is_active'] ? 0 : 1,
                $mayor['name'],
            ])
            ->values()
            ->all();
    }
}
