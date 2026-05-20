<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_item_distributions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The COB line being allocated — restrict delete to protect audit trail
            $table->foreignUuid('cob_item_id')
                  ->constrained('cob_items')
                  ->restrictOnDelete();

            // The receiving office (normalized from employees.office_division)
            $table->foreignId('office_id')
                  ->constrained('offices')
                  ->restrictOnDelete();

            // The end-user employee — nullable for office-pooled/general stock
            $table->foreignId('employee_id')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();

            // Quantity tracking
            $table->integer('allocated_quantity');
            $table->integer('procured_quantity')->default(0);

            // The Lock: NULL = free to edit/realign, non-NULL = locked inside a PR
            $table->foreignUuid('pr_item_id')
                  ->nullable()
                  ->constrained('pr_items')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_item_distributions');
    }
};
