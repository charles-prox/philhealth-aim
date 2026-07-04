<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            // Tracks when the Procurement Officer (GSU) accepted and assigned a PR number
            $table->timestamp('gsu_accepted_at')->nullable()->after('approved_signed_at');
            $table->unsignedBigInteger('gsu_accepted_by_id')->nullable()->after('gsu_accepted_at');
            $table->foreign('gsu_accepted_by_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropForeign(['gsu_accepted_by_id']);
            $table->dropColumn(['gsu_accepted_at', 'gsu_accepted_by_id']);
        });
    }
};
