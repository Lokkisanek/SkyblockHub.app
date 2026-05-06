<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_activity')) {
            return;
        }

        Schema::create('user_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('session_minutes_played')->default(0)->comment('Total minutes spent with app open in current day');
            $table->integer('profile_views_today')->default(0)->comment('Number of profile views in current day');
            $table->date('tracked_date')->index()->comment('Date for daily activity');
            $table->timestamps();

            $table->unique(['user_id', 'tracked_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity');
    }
};
