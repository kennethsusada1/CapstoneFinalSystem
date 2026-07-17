<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_records', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->nullable();
            $table->string('office')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_status')->default('Active');
            $table->string('source')->default('HRMS Import');
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_records');
    }
};
