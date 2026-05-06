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
        Schema::table('users', function (Blueprint $table) {
            $table->string('app_vip_rank')->nullable()->after('karma_score')->comment('VIP/MVP tier in the application');
            $table->boolean('is_donator')->default(false)->after('app_vip_rank')->comment('Whether user contributed via Buy Me Coffee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_vip_rank', 'is_donator']);
        });
    }
};
