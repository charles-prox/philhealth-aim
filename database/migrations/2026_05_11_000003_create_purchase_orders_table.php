<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('procurement_folders')->onDelete('cascade');
            $table->string('po_number')->unique();
            $table->string('supplier_id')->index(); // Assuming string ID or another table
            $table->decimal('total_amount', 15, 2);
            $table->string('mode_of_procurement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
