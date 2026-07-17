<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposed_training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_development_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('pending');
            $table->text('review_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_training_programs');
    }
};
