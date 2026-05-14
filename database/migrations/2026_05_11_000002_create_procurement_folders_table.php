<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_folders', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number')->unique();
            $table->string('rfq_control_no')->unique();
            $table->enum('status', ['PENDING', 'RFQ', 'AWARDED', 'PO', 'COMPLETED'])->default('PENDING');
            $table->string('google_sheet_id')->nullable();
            $table->string('google_form_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_folders');
    }
};
