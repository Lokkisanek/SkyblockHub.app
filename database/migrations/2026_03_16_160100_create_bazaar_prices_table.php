<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bazaar_prices', function (Blueprint $table) {
            $table->string('product_id')->primary();
            $table->decimal('buy_price', 18, 4)->default(0);
            $table->decimal('sell_price', 18, 4)->default(0);
            $table->unsignedBigInteger('buy_volume')->default(0);
            $table->unsignedBigInteger('sell_volume')->default(0);
            $table->unsignedInteger('buy_orders')->default(0);
            $table->unsignedInteger('sell_orders')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->foreign('product_id')->references('product_id')->on('bazaar_products')->cascadeOnDelete();

            $table->index('product_id');
            $table->index('buy_price');
            $table->index('sell_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bazaar_prices');
    }
};
