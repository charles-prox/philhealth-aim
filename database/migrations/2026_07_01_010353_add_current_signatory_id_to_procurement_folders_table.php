<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->foreignId('current_signatory_id')
                ->nullable()
                ->after('created_by_id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropForeign(['current_signatory_id']);
            $table->dropColumn('current_signatory_id');
        });
    }
};
