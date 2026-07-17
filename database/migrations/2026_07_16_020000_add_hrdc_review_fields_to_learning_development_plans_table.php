<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_development_plans', function (Blueprint $table) {
            $table->string('review_status')->default('pending')->after('status');
            $table->text('review_remarks')->nullable()->after('review_status');
            $table->foreignId('reviewed_by')->nullable()->after('review_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('learning_development_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'review_remarks', 'reviewed_at']);
        });
    }
};
