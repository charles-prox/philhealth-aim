<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_transactions', function (Blueprint $table) {
            // Link to the new DRAFT version this transaction produced
            if (!Schema::hasColumn('budget_transactions', 'version_id')) {
                $table->foreignUuid('version_id')->nullable()->constrained('cob_versions')->nullOnDelete();
            }
            // Optional free-text justification separate from the memo reference
            if (!Schema::hasColumn('budget_transactions', 'remarks')) {
                $table->text('remarks')->nullable()->after('reference_memo');
            }
            // Auditor: who submitted this realignment
            if (!Schema::hasColumn('budget_transactions', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['remarks']);
            $table->dropConstrainedForeignUuid('version_id');
        });
    }
};
