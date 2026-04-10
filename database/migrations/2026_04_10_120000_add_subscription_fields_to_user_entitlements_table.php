<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_entitlements', function (Blueprint $table) {
            $table->string('tier')->default('free')->after('status')->index();
            $table->timestamp('trial_started_at')->nullable()->after('current_period_ends_at');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_entitlements', function (Blueprint $table) {
            $table->dropIndex(['tier']);
            $table->dropIndex(['stripe_price_id']);
            $table->dropColumn([
                'tier',
                'trial_started_at',
                'trial_ends_at',
                'stripe_price_id',
            ]);
        });
    }
};
