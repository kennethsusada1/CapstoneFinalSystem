<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->renameColumn('predictive_skills_gap', 'prescriptive_skills_gap');
            $table->renameColumn('prescriptive_training_recommendation', 'predictive_training_recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table) {
            $table->renameColumn('prescriptive_skills_gap', 'predictive_skills_gap');
            $table->renameColumn('predictive_training_recommendation', 'prescriptive_training_recommendation');
        });
    }
};
