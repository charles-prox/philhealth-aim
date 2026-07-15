<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_item_distributions', function (Blueprint $table) {
            $table->foreignId('sub_employee_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cob_item_distributions', function (Blueprint $table) {
            $table->dropForeign(['sub_employee_id']);
            $table->dropColumn('sub_employee_id');
        });
    }
};
