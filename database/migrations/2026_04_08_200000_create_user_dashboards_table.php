<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Main Dashboard');
            $table->unsignedTinyInteger('slot_index')->default(1);
            $table->boolean('is_public')->default(false);
            $table->unsignedTinyInteger('grid_columns')->default(10);
            $table->unsignedTinyInteger('grid_rows')->default(10);
            $table->timestamps();

            $table->unique(['user_id', 'slot_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboards');
    }
};
