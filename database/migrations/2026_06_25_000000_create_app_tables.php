<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Parent Table: app_headers
        Schema::create('app_headers', function (Blueprint $table) {
            $table->id();
            $table->year('fiscal_year')->unique(); // Ensures only one APP configuration per operating year
            $table->boolean('is_approved')->default(false);
            $table->string('csv_file_path')->nullable();
            $table->string('scanned_pdf_path')->nullable(); // Path to the scanned approved copy
            $table->foreignId('uploaded_by_id')->constrained('employees');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 2. Child Table: app_line_items (Maps the 12 mandatory Central Office columns)
        Schema::create('app_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_header_id')->constrained('app_headers')->onDelete('cascade');
            
            // The 12 Mandatory APP Columns
            $table->string('project_title');
            $table->string('implementing_unit');
            $table->text('description');
            $table->string('procurement_mode');
            $table->boolean('is_epa')->default(false); // Early Procurement Activity (Yes/No)
            $table->text('evaluation_criteria')->nullable();
            $table->string('activity_start'); // Stored as string or date range reference
            $table->string('activity_end');
            $table->string('source_of_fund');
            $table->decimal('approved_budget', 15, 2);
            $table->string('strategy_tools')->nullable();
            $table->text('remarks')->nullable();
            
            // System Internal Budget Tracking
            $table->decimal('utilized_budget', 15, 2)->default(0.00); 
            $table->timestamps();
        });

        // 3. Update Existing pr_items Table
        Schema::table('pr_items', function (Blueprint $table) {
            $table->uuid('cob_item_id')->nullable()->change();
            $table->foreignId('app_line_item_id')->nullable()->after('id')->constrained('app_line_items');
        });
    }

    public function down(): void
    {
        Schema::table('pr_items', function (Blueprint $table) {
            $table->dropForeign(['app_line_item_id']);
            $table->dropColumn('app_line_item_id');
            // Revert cob_item_id to not nullable
            $table->uuid('cob_item_id')->nullable(false)->change();
        });

        Schema::dropIfExists('app_line_items');
        Schema::dropIfExists('app_headers');
    }
};
