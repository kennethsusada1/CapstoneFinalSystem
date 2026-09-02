<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table): void {
            $table->boolean('training_needed')->nullable()->after('prescriptive_training_recommendation');
            $table->decimal('training_need_probability', 5, 4)->nullable()->after('training_needed');
            $table->string('analytics_model_version', 64)->nullable()->after('training_need_probability');
        });
    }

    public function down(): void
    {
        Schema::table('learning_needs_analyses', function (Blueprint $table): void {
            $table->dropColumn([
                'training_needed',
                'training_need_probability',
                'analytics_model_version',
            ]);
        });
    }
};
