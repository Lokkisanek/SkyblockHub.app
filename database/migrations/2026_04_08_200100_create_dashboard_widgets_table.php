<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_dashboard_id')->constrained('user_dashboards')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title', 80)->nullable();
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->unsignedTinyInteger('w');
            $table->unsignedTinyInteger('h');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['user_dashboard_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
