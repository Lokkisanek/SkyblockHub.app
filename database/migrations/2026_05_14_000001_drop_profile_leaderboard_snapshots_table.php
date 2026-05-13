<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('profile_leaderboard_snapshots');
    }

    public function down(): void
    {
        // Intentionally empty: snapshots were removed with leaderboard period modes.
    }
};
