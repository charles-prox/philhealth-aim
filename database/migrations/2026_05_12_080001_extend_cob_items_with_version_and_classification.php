<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_items', function (Blueprint $table) {
            // Link to version/year system
            $table->foreignId('cob_version_id')->nullable()->after('id')->constrained('cob_versions')->nullOnDelete();
            $table->foreignId('budget_year_id')->nullable()->after('cob_version_id')->constrained('budget_years')->nullOnDelete();

            // WFP Excel columns
            $table->string('account_code')->nullable()->after('item_description')->index();
            $table->string('program_code')->nullable()->after('account_code');
            $table->string('expense_class')->nullable()->after('program_code'); // MOOE, CO, PS
            $table->string('unit_of_measure')->nullable()->after('expense_class');
            $table->decimal('quantity', 10, 2)->nullable()->after('unit_of_measure');
            $table->decimal('unit_cost', 15, 2)->nullable()->after('quantity');

            // Classification flags (Master DNA)
            $table->boolean('is_ict')->default(false)->after('remaining_amount');
            $table->boolean('is_semi_expendable')->default(false)->after('is_ict');
            $table->boolean('is_capital_expenditure')->default(false)->after('is_semi_expendable');
            $table->string('property_number')->nullable()->after('is_capital_expenditure');
        });
    }

    public function down(): void
    {
        Schema::table('cob_items', function (Blueprint $table) {
            $table->dropForeign(['cob_version_id']);
            $table->dropForeign(['budget_year_id']);
            $table->dropColumn([
                'cob_version_id', 'budget_year_id', 'account_code', 'program_code',
                'expense_class', 'unit_of_measure', 'quantity', 'unit_cost',
                'is_ict', 'is_semi_expendable', 'is_capital_expenditure', 'property_number',
            ]);
        });
    }
};
