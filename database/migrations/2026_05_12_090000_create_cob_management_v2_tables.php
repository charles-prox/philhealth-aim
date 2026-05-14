<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Drop existing if exists to ensure clean UUID start
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('cob_items');
        Schema::dropIfExists('cob_versions');
        Schema::dropIfExists('budget_years');

        // 1. budget_years
        Schema::create('budget_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('fiscal_year')->unique()->index();
            $table->enum('status', ['OPEN', 'LOCKED', 'CLOSED'])->default('OPEN');
            $table->decimal('total_allocation', 15, 2)->default(0);
            $table->timestamps();
        });

        // 2. cob_versions
        Schema::create('cob_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_year_id')->constrained('budget_years')->onDelete('cascade');
            $table->string('version_name');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // 3. cob_items
        Schema::create('cob_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('cob_versions')->onDelete('cascade');
            
            // Financials
            $table->decimal('recom_amount', 15, 2)->default(0);
            $table->decimal('encumbered_amount', 15, 2)->default(0);
            $table->decimal('actual_spent', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);

            // Classification
            $table->string('ppa_code')->index();
            $table->text('ppa_desc')->nullable();
            $table->string('sub_ppa_code')->nullable();
            $table->text('exp_desc')->nullable();
            $table->boolean('is_ict')->default(false)->index();
            $table->string('account')->nullable();
            $table->string('tier')->nullable();
            $table->string('class')->nullable();
            $table->string('gass')->nullable();

            // Identifiers
            $table->string('transaction_id')->nullable()->index();
            $table->string('work_and_financial_plan_id')->nullable();
            $table->string('office_id')->nullable();
            $table->string('sector')->nullable();

            // Particulars
            $table->text('full_particulars')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('recom_qty', 15, 2)->nullable();
            
            $table->timestamps();
        });

        // 4. budget_transactions
        Schema::create('budget_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['SUPPLEMENTAL', 'REALIGNMENT', 'REVISION_TRANSFER']);
            $table->foreignUuid('source_item_id')->nullable()->constrained('cob_items');
            $table->foreignUuid('target_item_id')->constrained('cob_items');
            $table->decimal('amount', 15, 2);
            $table->string('reference_memo');
            $table->string('memo_attachment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('cob_items');
        Schema::dropIfExists('cob_versions');
        Schema::dropIfExists('budget_years');
    }
};
