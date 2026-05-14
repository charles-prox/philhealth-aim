<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_accountabilities', function (Blueprint $table) {
            $table->id();
            $table->string('doc_number')->unique();
            $table->enum('doc_type', ['ICS', 'PAR']);
            $table->foreignId('end_user_id')->constrained('employees')->onDelete('cascade'); // Permanent Staff
            $table->foreignId('sub_user_id')->nullable()->constrained('employees')->onDelete('set null'); // JO/Casual
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_accountabilities');
    }
};
