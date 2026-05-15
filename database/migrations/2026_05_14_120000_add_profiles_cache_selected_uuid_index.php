<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speeds up leaderboard base query: WHERE selected = 1 GROUP BY minecraft_uuid.
     */
    public function up(): void
    {
        Schema::table('profiles_cache', function (Blueprint $table) {
            $table->index(['selected', 'minecraft_uuid'], 'profiles_cache_selected_minecraft_uuid_index');
        });
    }

    public function down(): void
    {
        Schema::table('profiles_cache', function (Blueprint $table) {
            $table->dropIndex('profiles_cache_selected_minecraft_uuid_index');
        });
    }
};
