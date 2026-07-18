<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable — only populated when a PMS employee is linked to an L&D account.
            // Unique because one PMS user maps to at most one L&D account.
            $table->unsignedBigInteger('pms_user_id')->nullable()->unique()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pms_user_id');
        });
    }
};
