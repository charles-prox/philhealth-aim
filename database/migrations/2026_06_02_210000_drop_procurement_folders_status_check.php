<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procurement_folders DROP CONSTRAINT IF EXISTS procurement_folders_status_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Restore check constraint if needed
            DB::statement("ALTER TABLE procurement_folders ADD CONSTRAINT procurement_folders_status_check CHECK (status::text = ANY (ARRAY['DRAFT'::text, 'PR_PRINTED'::text, 'RFQ_SENT'::text, 'AWARDED'::text, 'PO_RELEASED'::text]))");
        }
    }
};
