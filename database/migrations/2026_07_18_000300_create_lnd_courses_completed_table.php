<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lnd_courses_completed', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_referral_id')
                ->constrained('training_referrals')
                ->cascadeOnDelete();

            $table->string('course_code', 64)->nullable();
            $table->string('title', 255)->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('training_referral_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lnd_courses_completed');
    }
};
