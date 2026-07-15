<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_tasks', function (Blueprint $table) {
            $table->id();

            // Account-Based Routing Node
            $table->foreignId('target_employee_id')->constrained('employees')->onDelete('cascade');

            // Polymorphic Matrix Mapping
            $table->string('document_type'); // e.g., 'App\Models\ProcurementFolder'
            $table->string('document_id');

            // Fast Performance Index Cache
            $table->string('tracking_number');   // e.g., "PR-2026-07-0045"
            $table->string('document_label');    // e.g., "Purchase Request"
            $table->string('originating_office'); // e.g., "LO", "GSU"

            // Strict Verification Variables
            $table->timestamp('viewed_at')->nullable();
            $table->foreignId('viewed_by_employee_id')->nullable()->constrained('employees');

            $table->enum('status', ['PENDING', 'SIGNED', 'REJECTED', 'BYPASSED'])->default('PENDING');
            $table->timestamps();

            // High-Performance Optimization Compound Index
            $table->index(['target_employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_tasks');
    }
};
