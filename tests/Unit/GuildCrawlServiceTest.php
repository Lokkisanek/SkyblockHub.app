<?php

namespace Tests\Unit;

use App\Services\GuildCrawlService;
use PHPUnit\Framework\TestCase;

class GuildCrawlServiceTest extends TestCase
{
    public function test_member_uuids_from_guild_members_array(): void
    {
        $guild = [
            'members' => [
                ['uuid' => '550E8400-E29B-41D4-A716-446655440000', 'rank' => 'Member'],
                ['uuid' => '00000000-0000-0000-0000-000000000001', 'rank' => 'Officer'],
            ],
        ];

        $uuids = GuildCrawlService::memberUuidsFromGuild($guild);

        $this->assertSame([
            '550e8400e29b41d4a716446655440000',
            '00000000000000000000000000000001',
        ], $uuids);
    }

    public function test_member_uuids_from_guild_members_map(): void
    {
        $guild = [
            'members' => [
                '550E8400-E29B-41D4-A716-446655440000' => ['rank' => 'Member'],
            ],
        ];

        $uuids = GuildCrawlService::memberUuidsFromGuild($guild);

        $this->assertSame(['550e8400e29b41d4a716446655440000'], $uuids);
    }

    public function test_member_uuids_returns_empty_without_members(): void
    {
        $this->assertSame([], GuildCrawlService::memberUuidsFromGuild([]));
        $this->assertSame([], GuildCrawlService::memberUuidsFromGuild(['members' => null]));
    }

    public function test_parse_guild_names_option_splits_comma_list(): void
    {
        $names = GuildCrawlService::parseGuildNamesOption('Alpha, Beta ,Gamma');

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $names);
    }
}
