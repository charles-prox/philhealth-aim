<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('inventory_stocks')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('inventory_units')->onDelete('cascade');
            $table->enum('type', ['IN', 'OUT', 'RETURN', 'ADJUST']);
            $table->integer('qty');
            $table->string('reference_no'); // IAR/RIS/PRS
            $table->foreignId('recipient_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledgers');
    }
};
