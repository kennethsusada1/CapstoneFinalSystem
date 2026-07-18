<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('lnd_reference_id', 64)->unique();         // e.g. "LND-REF-2026-00042"
            $table->string('external_plan_id', 64);                   // "PMS-DP-42" from PMS
            $table->string('source_system', 32)->default('PMS');

            // PMS identity cross-link
            $table->unsignedBigInteger('pms_user_id');                // employee.id from PMS payload
            $table->unsignedBigInteger('pms_period_id');              // period.id
            $table->string('period_name', 128)->nullable();

            // Employee snapshot (denormalised, as received)
            $table->string('employee_name', 255)->nullable();
            $table->string('employee_email', 255)->nullable();
            $table->string('employee_position', 255)->nullable();
            $table->unsignedInteger('employee_office_id')->nullable();
            $table->string('employee_office', 255)->nullable();

            // Performance snapshot
            $table->decimal('official_score', 5, 2)->nullable();
            $table->string('official_rating', 64)->nullable();

            // Full JSON payloads for Secretariat display
            $table->json('ipcr_snapshot');                            // full ipcr block from PMS
            $table->json('idp_rows');                                 // full idp_rows array from PMS

            // Lifecycle
            $table->string('status', 64)->default('received');        // received | in_progress | completed
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            // PMS callback tracking
            $table->timestamp('pms_notified_at')->nullable();
            $table->text('pms_notify_error')->nullable();

            $table->timestamps();

            $table->index('pms_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_referrals');
    }
};
