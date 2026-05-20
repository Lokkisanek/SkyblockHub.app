<?php

namespace App\Services;

class TrustIndexService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function scammersByLookupKey(): array
    {
        $index = [];

        foreach (config('trust_index.scammers', []) as $scammer) {
            $username = (string) ($scammer['minecraft_username'] ?? '');
            if ($username === '') {
                continue;
            }

            $entry = $this->normalizeScammer($scammer);
            $index[strtolower($username)] = $entry;

            $uuid = strtolower((string) ($scammer['player_uuid'] ?? ''));
            if ($uuid !== '') {
                $index[$uuid] = $entry;
            }

            foreach ($scammer['aliases'] ?? [] as $alias) {
                $key = strtolower(trim((string) $alias));
                if ($key !== '') {
                    $index[$key] = $entry;
                }
            }
        }

        return $index;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookup(string $query): ?array
    {
        $key = strtolower(trim($query));
        if ($key === '') {
            return null;
        }

        return $this->scammersByLookupKey()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $scammer
     * @return array<string, mixed>
     */
    private function normalizeScammer(array $scammer): array
    {
        $categories = config('trust_index.incident_categories', []);

        $reports = collect($scammer['reports'] ?? [])
            ->map(function (array $report) use ($categories) {
                $categoryKey = (string) ($report['category'] ?? '');
                $categoryMeta = $categories[$categoryKey] ?? null;

                return [
                    ...$report,
                    'category_label' => $categoryMeta['label'] ?? $categoryKey,
                ];
            })
            ->values()
            ->all();

        return [
            'minecraft_username' => $scammer['minecraft_username'],
            'player_uuid' => $scammer['player_uuid'] ?? null,
            'aliases' => $scammer['aliases'] ?? [],
            'listed_since' => $scammer['listed_since'] ?? null,
            'severity_level' => $scammer['severity_level'] ?? 'HIGH',
            'risk_score' => (int) ($scammer['risk_score'] ?? 0),
            'status' => $scammer['status'] ?? 'CONFIRMED',
            'summary' => $scammer['summary'] ?? '',
            'reports' => $reports,
        ];
    }
}
