<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mayors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('uuid')->nullable();
            $table->json('perks_json')->nullable();
            $table->json('election_raw')->nullable();
            $table->timestamp('last_updated')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mayors');
    }
};
