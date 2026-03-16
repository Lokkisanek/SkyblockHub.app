<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bazaar_products', function (Blueprint $table) {
            $table->string('product_id')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('npc_sell_price', 18, 4)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bazaar_products');
    }
};
