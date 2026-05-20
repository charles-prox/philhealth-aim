<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds the "Recommended By" signatory columns to procurement_folders.
     * Mirrors the existing requested_by_id / approved_by_id pattern with
     * a snapshot designation field for COA-level audit integrity.
     */
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            // FK to employees (BigInt PK, so foreignId not foreignUuid)
            $table->foreignId('recommended_by_id')
                  ->nullable()
                  ->after('approved_by_designation')
                  ->constrained('employees');

            // Snapshot of the designation at the moment of PR generation
            $table->string('recommended_by_designation')
                  ->nullable()
                  ->after('recommended_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropForeign(['recommended_by_id']);
            $table->dropColumn(['recommended_by_id', 'recommended_by_designation']);
        });
    }
};
