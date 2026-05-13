<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_presence', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->unsignedInteger('last_seen')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_presence');
    }
};
