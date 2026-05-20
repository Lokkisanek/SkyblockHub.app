<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_index_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index();
            $table->string('status', 16)->default('pending')->index();
            $table->string('minecraft_username');
            $table->string('submitter_name')->nullable();
            $table->string('submitter_contact')->nullable();
            $table->string('category')->nullable();
            $table->text('description');
            $table->text('evidence')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_index_submissions');
    }
};
