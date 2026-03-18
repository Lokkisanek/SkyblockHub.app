<?php

namespace App\Services;

class SkyblockTimeService
{
    public const DAY_SECONDS = 1200;
    public const YEAR_DAYS = 372;
    public const YEAR_SECONDS = self::DAY_SECONDS * self::YEAR_DAYS;

    // This aligns with existing event timer epoch in the frontend.
    private const SKYBLOCK_EPOCH_UNIX = 1560275700;

    // Election cycle defaults (SkyBlock day-of-year) used as robust fallback.
    private const ELECTION_START_DAY = 86;
    private const ELECTION_END_DAY = 89;
    private const MAYOR_OFFICE_DAY = 94;

    public function getSkyblockDateFromUnix(int $unix): array
    {
        $delta = max(0, $unix - self::SKYBLOCK_EPOCH_UNIX);
        $yearIndex = intdiv($delta, self::YEAR_SECONDS);
        $secondsIntoYear = $delta % self::YEAR_SECONDS;
        $dayOfYear = intdiv($secondsIntoYear, self::DAY_SECONDS) + 1;

        return [
            'year' => $yearIndex + 1,
            'day_of_year' => $dayOfYear,
            'seconds_into_day' => $secondsIntoYear % self::DAY_SECONDS,
        ];
    }

    public function secondsUntil(int $unix): int
    {
        return max(0, $unix - time());
    }

    public function getElectionTimeline(?int $referenceUnix = null): array
    {
        $now = $referenceUnix ?? time();
        $sky = $this->getSkyblockDateFromUnix($now);

        $candidateYears = [
            max(1, $sky['year'] - 1),
            $sky['year'],
            $sky['year'] + 1,
        ];

        $best = null;

        foreach ($candidateYears as $year) {
            $yearStart = self::SKYBLOCK_EPOCH_UNIX + (($year - 1) * self::YEAR_SECONDS);
            $startUnix = $yearStart + ((self::ELECTION_START_DAY - 1) * self::DAY_SECONDS);
            $endUnix = $yearStart + ((self::ELECTION_END_DAY - 1) * self::DAY_SECONDS);
            $officeUnix = $yearStart + ((self::MAYOR_OFFICE_DAY - 1) * self::DAY_SECONDS);

            if ($now <= ($officeUnix + self::DAY_SECONDS)) {
                $best = [
                    'skyblock_year' => $year,
                    'start_unix' => $startUnix,
                    'end_unix' => $endUnix,
                    'office_unix' => $officeUnix,
                ];
                break;
            }
        }

        if (! $best) {
            $year = $sky['year'] + 1;
            $yearStart = self::SKYBLOCK_EPOCH_UNIX + (($year - 1) * self::YEAR_SECONDS);
            $best = [
                'skyblock_year' => $year,
                'start_unix' => $yearStart + ((self::ELECTION_START_DAY - 1) * self::DAY_SECONDS),
                'end_unix' => $yearStart + ((self::ELECTION_END_DAY - 1) * self::DAY_SECONDS),
                'office_unix' => $yearStart + ((self::MAYOR_OFFICE_DAY - 1) * self::DAY_SECONDS),
            ];
        }

        $phase = 'upcoming';
        $secondsRemaining = $this->secondsUntil($best['start_unix']);

        if ($now >= $best['start_unix'] && $now < $best['end_unix']) {
            $phase = 'election_live';
            $secondsRemaining = $this->secondsUntil($best['end_unix']);
        } elseif ($now >= $best['end_unix'] && $now < $best['office_unix']) {
            $phase = 'mayor_transition';
            $secondsRemaining = $this->secondsUntil($best['office_unix']);
        }

        return [
            ...$best,
            'phase' => $phase,
            'seconds_remaining' => $secondsRemaining,
        ];
    }
}
