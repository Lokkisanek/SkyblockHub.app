<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('minecraft_uuid', 32)->comment('Normalized 32-char hex, no hyphens');
            $table->date('snapshot_on');
            $table->unsignedInteger('skyblock_level')->default(0);
            $table->unsignedBigInteger('networth')->default(0);
            $table->unsignedBigInteger('non_cosmetic_networth')->default(0);
            $table->unsignedInteger('account_age_days')->default(0);
            $table->decimal('skill_average', 10, 2)->default(0);
            $table->unsignedBigInteger('weight')->default(0);
            $table->unsignedBigInteger('slayer_total')->default(0);
            $table->boolean('is_app_user')->default(false);
            $table->timestamps();

            $table->unique(['minecraft_uuid', 'snapshot_on']);
            $table->index('snapshot_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_leaderboard_snapshots');
    }
};
