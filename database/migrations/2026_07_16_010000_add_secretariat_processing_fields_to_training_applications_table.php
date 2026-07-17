<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_applications', function (Blueprint $table) {
            $table->text('process_remarks')->nullable()->after('status');
            $table->foreignId('processed_by')->nullable()->after('process_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('processed_by');
        });
    }

    public function down(): void
    {
        Schema::table('training_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['process_remarks', 'processed_at']);
        });
    }
};
