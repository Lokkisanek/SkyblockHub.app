<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trial_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('discord_id')->unique();
            $table->foreignId('first_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tier')->default('vip');
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_redemptions');
    }
};
