<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Drop existing to ensure a clean slate, using CASCADE for Postgres FKs
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS purchase_orders CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS distribution_plans CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS quotation_items CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS quotations CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS pr_items CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS procurement_folders CASCADE');
        \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS suppliers CASCADE');

        // 0. SUPPLIERS
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('philgeps_number')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        // 1. THE MASTER BUCKET
        Schema::create('procurement_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_number')->unique(); // PR No.
            $table->string('rfq_control_no')->unique()->nullable();
            $table->enum('status', ['DRAFT', 'PR_PRINTED', 'RFQ_SENT', 'AWARDED', 'PO_RELEASED'])->default('DRAFT');
            $table->text('overall_purpose')->nullable();
            $table->string('requesting_unit')->nullable(); // End-user Section/Division
            
            // Google Automation Links
            $table->string('google_form_id')->nullable();
            $table->string('google_sheet_id')->nullable();

            // RFQ Cover Page Details
            $table->date('geps_posting_from')->nullable();
            $table->date('geps_posting_to')->nullable();
            $table->date('submission_due_date')->nullable();
            
            // Historical Signatories (Snapshot logic)
            // Note: Employees uses BigInt, so we use foreignId, not foreignUuid here.
            $table->foreignId('requested_by_id')->nullable()->constrained('employees');
            $table->string('requested_by_designation')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('employees');
            $table->string('approved_by_designation')->nullable();
            $table->timestamps();
        });

        // 2. PR & PO ITEMS
        Schema::create('pr_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folder_id')->constrained('procurement_folders')->onDelete('cascade');
            
            // Link to the COB DNA (restrict on delete to prevent breaking audit)
            $table->foreignUuid('cob_item_id')->constrained('cob_items')->restrictOnDelete();
            
            $table->text('item_description_override')->nullable();
            $table->integer('total_qty')->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0); // Used for 50k Threshold Logic
            $table->timestamps();
        });

        // 3. EXTERNAL BIDS (Quotations)
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folder_id')->constrained('procurement_folders')->onDelete('cascade');
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->boolean('is_winning_bid')->default(false);
            
            // Per-Supplier Terms
            $table->string('delivery_period')->nullable();
            $table->string('warranty_terms')->nullable();
            $table->date('price_validity_to')->nullable();
            $table->timestamps();
        });

        // 4. LINE-ITEM BID PRICES
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained('quotations')->onDelete('cascade');
            $table->foreignUuid('pr_item_id')->constrained('pr_items')->onDelete('cascade');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();
        });

        // 5. THE DISTRIBUTION PLAN (Who gets what)
        Schema::create('distribution_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folder_id')->constrained('procurement_folders')->onDelete('cascade');
            
            // Employees uses BigInt
            $table->foreignId('employee_id')->constrained('employees'); 
            
            $table->foreignUuid('pr_item_id')->constrained('pr_items')->onDelete('cascade');
            $table->integer('planned_qty');
            $table->string('serial_no')->nullable(); // For ICS/PAR later
            $table->timestamps();
        });

        // 6. PURCHASE ORDERS
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folder_id')->constrained('procurement_folders')->onDelete('cascade');
            $table->string('po_number')->unique();
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('mode_of_procurement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('distribution_plans');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('pr_items');
        Schema::dropIfExists('procurement_folders');
        Schema::dropIfExists('suppliers');
    }
};
