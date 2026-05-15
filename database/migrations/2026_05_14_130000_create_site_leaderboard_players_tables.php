<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['site_leaderboard_players', true],
            ['site_leaderboard_players_staging', false],
        ] as [$name, $withSecondaryIndexes]) {
            Schema::create($name, function (Blueprint $table) use ($withSecondaryIndexes): void {
                $table->string('minecraft_uuid', 64)->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('linked_minecraft_uuid', 64)->nullable();
                $table->boolean('is_app_user')->default(false);
                $table->string('app_vip_rank', 32)->nullable();
                $table->boolean('is_donator')->default(false);
                $table->text('display_name')->nullable();
                $table->string('profile_username', 64)->nullable();
                $table->unsignedInteger('skyblock_level')->default(0);
                $table->unsignedBigInteger('networth')->default(0);
                $table->unsignedBigInteger('non_cosmetic_networth')->default(0);
                $table->unsignedInteger('account_age_days')->default(0);
                $table->decimal('skill_average', 10, 2)->default(0);
                $table->unsignedBigInteger('slayer_total')->default(0);
                $table->unsignedBigInteger('weight')->default(0);
                $table->boolean('online')->default(false);
                $table->unsignedBigInteger('last_seen_ts')->nullable();
                $table->text('hypixel_rank')->nullable();
                $table->string('hypixel_rank_color', 16)->default('#AAAAAA');
                $table->boolean('has_public_dashboard')->default(false);
                $table->timestamp('slice_max_fetched_at')->nullable();

                if ($withSecondaryIndexes) {
                    $table->index(['skyblock_level', 'networth'], 'slb_lvl_nw');
                    $table->index(['networth', 'skyblock_level'], 'slb_nw_lvl');
                    $table->index(['is_app_user', 'skyblock_level', 'networth'], 'slb_ia_lvl_nw');
                    $table->index(['is_app_user', 'networth', 'skyblock_level'], 'slb_ia_nw_lvl');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_leaderboard_players_staging');
        Schema::dropIfExists('site_leaderboard_players');
    }
};
