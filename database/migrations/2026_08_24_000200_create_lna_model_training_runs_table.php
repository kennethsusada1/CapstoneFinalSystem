<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lna_model_training_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('model_version', 64)->nullable();
            $table->string('status', 16);
            $table->string('trigger', 32)->default('scheduled');
            $table->unsignedInteger('source_rows')->default(0);
            $table->unsignedInteger('positive_rows')->default(0);
            $table->unsignedInteger('negative_rows')->default(0);
            $table->unsignedInteger('validation_rows')->nullable();
            $table->decimal('validation_roc_auc', 6, 4)->nullable();
            $table->json('validation_metrics')->nullable();
            $table->string('data_signature', 64)->nullable()->index();
            $table->string('artifact_path')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lna_model_training_runs');
    }
};
