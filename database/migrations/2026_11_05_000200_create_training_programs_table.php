<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('provider')->nullable();
            $table->string('training_type')->default('Invitational');
            $table->string('delivery_mode')->default('Face-to-face');
            $table->string('venue')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('slots')->default(0);
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('training_applications', function (Blueprint $table): void {
            $table->string('delivery_mode')->nullable()->after('training_type');
            $table->string('venue')->nullable()->after('provider');
            $table->foreignId('training_program_id')
                ->nullable()
                ->after('training_referral_id')
                ->constrained('training_programs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_program_id');
            $table->dropColumn(['delivery_mode', 'venue']);
        });

        Schema::dropIfExists('training_programs');
    }
};
