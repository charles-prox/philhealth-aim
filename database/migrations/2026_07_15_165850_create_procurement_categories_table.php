<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('uacs_code', 12);
            $table->string('budget_class'); // MOOE, CAPITAL_OUTLAY
            $table->string('tracking_type'); // CONSUMABLE, UTILITY, CONTRACT, SERVICE, ICS, PAR
            $table->string('audit_requirement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_categories');
    }
};
