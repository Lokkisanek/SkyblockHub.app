<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bazaar_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('bazaar_prices', 'buy_moving_week')) {
                $table->decimal('buy_moving_week', 22, 4)->default(0)->after('sell_volume');
            }

            if (! Schema::hasColumn('bazaar_prices', 'sell_moving_week')) {
                $table->decimal('sell_moving_week', 22, 4)->default(0)->after('buy_moving_week');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bazaar_prices', function (Blueprint $table) {
            if (Schema::hasColumn('bazaar_prices', 'sell_moving_week')) {
                $table->dropColumn('sell_moving_week');
            }

            if (Schema::hasColumn('bazaar_prices', 'buy_moving_week')) {
                $table->dropColumn('buy_moving_week');
            }
        });
    }
};
