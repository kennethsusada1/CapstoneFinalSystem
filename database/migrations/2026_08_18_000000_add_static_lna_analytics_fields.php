<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->text('predictive_skills_gap')->nullable()->after('competency_gap');
            $table->string('prescriptive_training_recommendation')->nullable()->after('predictive_skills_gap');
            $table->dateTime('analytics_generated_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'predictive_skills_gap',
                'prescriptive_training_recommendation',
                'analytics_generated_at',
            ]);
        });
    }
};
