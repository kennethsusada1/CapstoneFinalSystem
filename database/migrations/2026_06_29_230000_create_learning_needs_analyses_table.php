<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_needs_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id')->nullable()->index();
            $table->string('focus_area');
            $table->text('competency_gap');
            $table->text('proposed_intervention');
            $table->string('priority_level')->default('medium');
            $table->string('status')->default('submitted');
            $table->date('submitted_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_needs_analyses');
    }
};
