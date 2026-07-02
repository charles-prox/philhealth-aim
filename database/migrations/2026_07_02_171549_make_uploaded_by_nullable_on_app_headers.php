<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes uploaded_by_id nullable so admins without a linked Employee record
     * can still upload APP files. The field is still a FK to employees when set.
     */
    public function up(): void
    {
        Schema::table('app_headers', function (Blueprint $table) {
            $table->foreignId('uploaded_by_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_headers', function (Blueprint $table) {
            $table->foreignId('uploaded_by_id')->nullable(false)->change();
        });
    }
};
