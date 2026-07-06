<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->string('budget_cob_year')->nullable()->after('budget_code');
            $table->text('budget_remarks')->nullable()->after('budget_cob_year');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropColumn(['budget_cob_year', 'budget_remarks']);
        });
    }
};
