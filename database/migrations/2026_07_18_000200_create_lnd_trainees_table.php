<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lnd_trainees', function (Blueprint $table) {
            $table->id();

            // Cross-system identity map — one row per employee
            $table->unsignedBigInteger('pms_user_id')->unique();      // stable FK to PMS users.id
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('office_name', 255)->nullable();

            // Set by Secretariat when the employee's L&D account is manually activated
            $table->foreignId('lnd_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lnd_trainees');
    }
};
