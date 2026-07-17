<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id')->nullable()->index();
            $table->string('training_title');
            $table->text('implementation_summary');
            $table->text('learning_outcomes')->nullable();
            $table->string('status')->default('draft');
            $table->date('submitted_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_action_plans');
    }
};
