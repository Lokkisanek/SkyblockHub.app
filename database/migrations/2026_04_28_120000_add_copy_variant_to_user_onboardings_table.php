<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->string('copy_variant', 20)->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_onboardings', function (Blueprint $table) {
            $table->dropColumn('copy_variant');
        });
    }
};
