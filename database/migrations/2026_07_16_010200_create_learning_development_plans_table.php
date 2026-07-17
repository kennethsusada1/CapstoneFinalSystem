<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('planning_year', 4);
            $table->text('objectives');
            $table->text('priority_programs');
            $table->text('budget_notes')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_development_plans');
    }
};
