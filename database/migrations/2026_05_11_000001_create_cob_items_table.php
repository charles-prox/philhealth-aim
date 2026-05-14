<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_items', function (Blueprint $table) {
            $table->id();
            $table->year('budget_year')->index();
            $table->string('item_description')->index();
            $table->decimal('allocated_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_items');
    }
};
