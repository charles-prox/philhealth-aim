<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            // Drop the old, single, insufficient event_date column if it exists
            if (Schema::hasColumn('procurement_folders', 'event_date')) {
                $table->dropColumn('event_date');
            }

            // Create separate indexed date fields for range tracking
            $table->date('event_start_date')->nullable()->index();
            $table->date('event_end_date')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropColumn(['event_start_date', 'event_end_date']);
            $table->date('event_date')->nullable();
        });
    }
};
