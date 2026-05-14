<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('fullname')->index();
            $table->string('designation');
            $table->string('salary_grade')->nullable();
            $table->string('office_division')->index();
            $table->string('sub_office')->nullable()->index();
            $table->enum('employment_status', ['PERMANENT', 'CASUAL', 'JO']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
