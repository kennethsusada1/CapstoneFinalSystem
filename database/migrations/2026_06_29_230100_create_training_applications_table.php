<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id')->nullable()->index();
            $table->string('training_title');
            $table->string('training_type');
            $table->string('provider')->nullable();
            $table->string('office')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('status')->default('applied');
            $table->boolean('is_attended')->default(false);
            $table->date('completed_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_applications');
    }
};
