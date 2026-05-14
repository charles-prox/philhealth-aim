<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_years', function (Blueprint $table) {
            $table->id();
            $table->year('year')->unique()->index();
            $table->string('label')->nullable();         // e.g. "FY 2026"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('cob_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_year_id')->constrained('budget_years')->cascadeOnDelete();
            $table->string('version_name');              // Original, Supplemental I, Realignment I
            $table->enum('version_type', ['original', 'supplemental', 'realignment'])->default('original');
            $table->enum('status', ['draft', 'processing', 'active', 'superseded'])->default('draft');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('total_allocation', 18, 2)->default(0);
            $table->string('source_file')->nullable();   // original filename
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_versions');
        Schema::dropIfExists('budget_years');
    }
};
