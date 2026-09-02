<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lna_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_needs_analysis_id')
                ->constrained('learning_needs_analyses')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->string('competency_name');
            $table->string('competency_category')->nullable();
            $table->decimal('probability', 5, 4);
            $table->string('priority', 16);
            $table->string('training_title');
            $table->string('training_type')->default('In-house');
            $table->string('provider')->nullable();
            $table->text('recommendation_text');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['learning_needs_analysis_id', 'rank']);
            $table->index(['learning_needs_analysis_id', 'probability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lna_recommendations');
    }
};
