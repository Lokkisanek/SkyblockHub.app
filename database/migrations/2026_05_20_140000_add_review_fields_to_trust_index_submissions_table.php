<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trust_index_submissions', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('user_id');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('trust_index_submissions', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_at', 'reviewed_by', 'admin_notes']);
        });
    }
};
