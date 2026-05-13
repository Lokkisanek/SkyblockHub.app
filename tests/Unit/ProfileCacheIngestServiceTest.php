<?php

namespace Tests\Unit;

use App\Services\ProfileCacheIngestService;
use PHPUnit\Framework\TestCase;

class ProfileCacheIngestServiceTest extends TestCase
{
    public function test_skyblock_leaderboards_payload_collects_uuids_from_skyblock_boards_only(): void
    {
        $payload = [
            'success' => true,
            'leaderboards' => [
                'SKYBLOCK_LEVEL_ALLTIME' => [
                    ['uuid' => '550E8400-E29b-41D4-A716-446655440000'],
                ],
                'GENERIC_BOARD' => [
                    ['uuid' => '00000000000000000000000000000001'],
                ],
            ],
        ];

        $uuids = ProfileCacheIngestService::skyblockUuidsFromLeaderboardsPayload($payload);

        $this->assertSame(['550e8400e29b41d4a716446655440000'], $uuids);
    }

    public function test_skyblock_leaderboards_payload_returns_empty_for_invalid_input(): void
    {
        $this->assertSame([], ProfileCacheIngestService::skyblockUuidsFromLeaderboardsPayload(null));
        $this->assertSame([], ProfileCacheIngestService::skyblockUuidsFromLeaderboardsPayload([]));
    }
}
