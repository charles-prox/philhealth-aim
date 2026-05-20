<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend procurement_folders with new Distribution-First fields
        // Kept additive — existing columns (tracking_number, overall_purpose, etc.) are untouched
        Schema::table('procurement_folders', function (Blueprint $table) {
            // Formal PR number assigned at generation time (auto-generated, unique)
            $table->string('pr_number')->unique()->nullable()->after('tracking_number');

            // Plain-language batch title for this PR folder
            $table->string('project_title')->nullable()->after('pr_number');

            // Procurement method chosen at compile time
            $table->string('procurement_method')->nullable()->after('project_title');
            // e.g. 'Shopping', 'SVP', 'Public Bidding', 'Direct Contracting'
        });

        // Extend pr_items with cost & accountability fields
        // Kept additive — existing columns (total_qty, unit_cost) are untouched
        Schema::table('pr_items', function (Blueprint $table) {
            // Explicit estimated costs (boot method will auto-compute estimated_total_cost)
            $table->decimal('estimated_unit_cost', 15, 2)->default(0)->after('unit_cost');
            $table->decimal('estimated_total_cost', 15, 2)->default(0)->after('estimated_unit_cost');

            // Auto-set by boot method via ₱50,000 COA threshold rule
            $table->string('accountability_type')->nullable()->after('estimated_total_cost');
            // 'ICS' (below 50k) or 'PAR' (50k and above)
        });
    }

    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropColumn(['pr_number', 'project_title', 'procurement_method']);
        });

        Schema::table('pr_items', function (Blueprint $table) {
            $table->dropColumn(['estimated_unit_cost', 'estimated_total_cost', 'accountability_type']);
        });
    }
};
