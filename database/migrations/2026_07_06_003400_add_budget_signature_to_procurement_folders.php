<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->timestamp('budget_signed_at')->nullable()->after('gsu_accepted_by_id');
            $table->unsignedBigInteger('budget_signed_by_id')->nullable()->after('budget_signed_at');
            $table->string('budget_ppa_code')->nullable()->after('budget_signed_by_id');
            $table->string('budget_code')->nullable()->after('budget_ppa_code');

            $table->foreign('budget_signed_by_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropForeign(['budget_signed_by_id']);
            $table->dropColumn(['budget_signed_at', 'budget_signed_by_id', 'budget_ppa_code', 'budget_code']);
        });
    }
};
