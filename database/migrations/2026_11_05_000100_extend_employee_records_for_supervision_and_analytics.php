<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_records', function (Blueprint $table): void {
            $table->string('supervisor_employee_id')->nullable()->index()->after('office');
            $table->string('education_level')->nullable()->after('position');
            $table->string('role_family')->nullable()->after('education_level');
            $table->unsignedTinyInteger('seniority_level')->nullable()->after('role_family');
            $table->date('hire_date')->nullable()->after('seniority_level');
        });

        Schema::create('employee_performance_records', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_id');
            $table->unsignedSmallInteger('review_year');
            $table->decimal('ipcr_rating', 5, 2);
            $table->string('source')->default('HRMS Import');
            $table->timestamps();

            $table->foreign('employee_id')->references('employee_id')->on('employee_records')->cascadeOnDelete();
            $table->unique(['employee_id', 'review_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_performance_records');

        Schema::table('employee_records', function (Blueprint $table): void {
            $table->dropIndex(['supervisor_employee_id']);
            $table->dropColumn([
                'supervisor_employee_id',
                'education_level',
                'role_family',
                'seniority_level',
                'hire_date',
            ]);
        });
    }
};
