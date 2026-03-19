<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bin_snapshots', function (Blueprint $table) {
            $table->string('item_id')->nullable()->after('item_name');
            $table->string('internal_name')->nullable()->after('item_id');
            $table->string('item_key')->nullable()->after('internal_name');

            $table->index(['item_key', 'price']);
            $table->index(['item_key', 'recorded_at']);
            $table->index('internal_name');
        });
    }

    public function down(): void
    {
        Schema::table('bin_snapshots', function (Blueprint $table) {
            $table->dropIndex(['item_key', 'price']);
            $table->dropIndex(['item_key', 'recorded_at']);
            $table->dropIndex(['internal_name']);

            $table->dropColumn(['item_key', 'internal_name', 'item_id']);
        });
    }
};
