<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_hub_connection', function (Blueprint $table) {
            $table->id();

            // Connection lifecycle status
            // disconnected | pending | connected | rejected
            $table->string('status', 32)->default('disconnected');

            // Credentials received from PMS in the connection-request payload
            $table->string('pms_base_url')->nullable();      // used to call back to PMS
            $table->text('pms_callback_token')->nullable();  // Bearer token for the callback

            // Lifecycle timestamps
            $table->timestamp('requested_at')->nullable();   // when PMS sent the request
            $table->timestamp('accepted_at')->nullable();    // when L&D admin accepted

            $table->timestamps();
        });

        // Seed the single row
        DB::table('pms_hub_connection')->insert([
            'status'     => 'disconnected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_hub_connection');
    }
};
