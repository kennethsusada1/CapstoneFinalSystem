<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_applications', function (Blueprint $table) {
            $table->foreignId('learning_needs_analysis_id')
                ->nullable()
                ->after('user_id')
                ->constrained('learning_needs_analyses')
                ->nullOnDelete();
            $table->string('secretariat_status')
                ->default('pending')
                ->after('status')
                ->index();
        });

        DB::table('training_applications')
            ->whereIn('status', ['ongoing', 'completed', 'rejected'])
            ->update(['secretariat_status' => 'processed']);

        Schema::table('learning_development_plans', function (Blueprint $table) {
            $table->foreignId('training_application_id')
                ->nullable()
                ->unique()
                ->after('submitted_by')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('learning_development_plans', function (Blueprint $table) {
            $table->dropUnique(['training_application_id']);
            $table->dropConstrainedForeignId('training_application_id');
        });

        Schema::table('training_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('learning_needs_analysis_id');
            $table->dropIndex(['secretariat_status']);
            $table->dropColumn('secretariat_status');
        });
    }
};
