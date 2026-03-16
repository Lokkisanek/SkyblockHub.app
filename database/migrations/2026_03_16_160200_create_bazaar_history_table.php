<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bazaar_history', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->decimal('buy_price', 18, 4)->default(0);
            $table->decimal('sell_price', 18, 4)->default(0);
            $table->timestamp('recorded_at')->index();

            $table->foreign('product_id')->references('product_id')->on('bazaar_products')->cascadeOnDelete();
            $table->index(['product_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bazaar_history');
    }
};
