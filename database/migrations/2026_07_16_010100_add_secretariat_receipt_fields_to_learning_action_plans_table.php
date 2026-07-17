<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_action_plans', function (Blueprint $table) {
            $table->string('receipt_status')->default('pending')->after('status');
            $table->text('receipt_remarks')->nullable()->after('receipt_status');
            $table->foreignId('received_by')->nullable()->after('receipt_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('learning_action_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['receipt_status', 'receipt_remarks', 'received_at']);
        });
    }
};
