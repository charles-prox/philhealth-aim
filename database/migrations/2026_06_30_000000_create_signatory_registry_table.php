<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatory_registry', function (Blueprint $table) {
            $table->id();

            // Machine-readable slot identifier. Unique per office for DIVISION_CHIEF; globally unique for others.
            $table->string('position_code')->index();

            // Human-readable label displayed in the UI and printed on PDF forms.
            $table->string('position_title');

            // Nullable: populated only for office-scoped slots (e.g., DIVISION_CHIEF).
            // Null for regional-level slots (RVP, MSD_HEAD, GSU_HEAD, BUDGET_OFFICER).
            $table->foreignId('office_id')->nullable()->constrained('offices')->onDelete('cascade');

            // ─── 3-Tier Execution Chain ────────────────────────────────────────────────
            // The permanent post-holder. Required; always the fallback when OICs are unset.
            $table->foreignId('primary_employee_id')->constrained('employees');

            // First OIC designation. Nullable: only populated during active OIC coverage.
            $table->foreignId('oic_primary_employee_id')->nullable()->constrained('employees');

            // Second OIC fallback. Nullable: used when OIC_1 is also unavailable.
            $table->foreignId('oic_secondary_employee_id')->nullable()->constrained('employees');

            // ─── Active Holder Switchboard ─────────────────────────────────────────────
            // Controls which employee's name appears on printed PR documents.
            // Changing this field is the only action needed to rotate signing authority.
            $table->enum('active_holder', ['PRIMARY', 'OIC_1', 'OIC_2'])->default('PRIMARY');

            $table->timestamps();

            // Composite unique: allows one DIVISION_CHIEF per office, one regional slot per code.
            $table->unique(['position_code', 'office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatory_registry');
    }
};
