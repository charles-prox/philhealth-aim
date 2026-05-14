<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('inventory_stocks')->onDelete('cascade');
            $table->string('serial_number')->unique()->nullable();
            $table->string('property_number')->unique()->nullable();
            $table->enum('status', ['STOCK', 'ISSUED', 'REPAIR', 'DISPOSED', 'RETURNED'])->default('STOCK');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
