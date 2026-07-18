<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_applications', function (Blueprint $table) {
            // Nullable FK — set when a training application is created from a PMS referral.
            // Existing self-submitted applications have no referral, so this stays null.
            $table->foreignId('training_referral_id')
                ->nullable()
                ->after('user_id')
                ->constrained('training_referrals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_referral_id');
        });
    }
};
