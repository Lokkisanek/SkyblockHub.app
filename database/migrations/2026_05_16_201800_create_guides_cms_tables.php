<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->string('category')->default('meta')->index();
            $table->string('category_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('published')->index();
            $table->date('last_updated_on')->nullable();
            $table->json('sections');
            $table->json('useful_links')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category', 'sort_order']);
        });

        Schema::create('guide_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->foreignId('guide_id')->nullable()->constrained('guides')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_contact')->nullable();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('category')->default('meta');
            $table->string('category_label')->nullable();
            $table->json('sections');
            $table->json('useful_links')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type', 'created_at']);
        });

        Schema::create('guide_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->foreignId('guide_submission_id')->nullable()->constrained('guide_submissions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('description', 500)->nullable();
            $table->string('category');
            $table->string('category_label')->nullable();
            $table->json('sections');
            $table->json('useful_links')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_revisions');
        Schema::dropIfExists('guide_submissions');
        Schema::dropIfExists('guides');
    }
};
