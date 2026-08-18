<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->string('ipcr_rating')->nullable()->after('employee_id');
            $table->json('core_functions')->nullable()->after('ipcr_rating');
            $table->json('support_functions')->nullable()->after('core_functions');
            $table->json('skill_assessments')->nullable()->after('support_functions');
            $table->json('preferred_learning_methods')->nullable()->after('skill_assessments');
            $table->string('preferred_learning_methods_other')->nullable()->after('preferred_learning_methods');
            $table->json('assessment_methods')->nullable()->after('preferred_learning_methods_other');
            $table->string('employee_signature')->nullable()->after('assessment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'ipcr_rating',
                'core_functions',
                'support_functions',
                'skill_assessments',
                'preferred_learning_methods',
                'preferred_learning_methods_other',
                'assessment_methods',
                'employee_signature',
            ]);
        });
    }
};
