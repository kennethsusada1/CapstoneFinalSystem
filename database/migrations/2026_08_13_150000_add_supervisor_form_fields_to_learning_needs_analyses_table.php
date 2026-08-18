<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->json('supervisor_skill_assessments')->nullable()->after('skill_assessments');
            $table->json('supervisor_recommendations')->nullable()->after('assessment_methods');
            $table->json('supervisor_assessment_methods')->nullable()->after('supervisor_recommendations');
            $table->string('supervisor_signature')->nullable()->after('employee_signature');
            $table->date('supervisor_signed_on')->nullable()->after('supervisor_signature');
        });
    }

    public function down(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'supervisor_skill_assessments',
                'supervisor_recommendations',
                'supervisor_assessment_methods',
                'supervisor_signature',
                'supervisor_signed_on',
            ]);
        });
    }
};
