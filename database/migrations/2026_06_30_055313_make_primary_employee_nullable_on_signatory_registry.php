<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing NOT NULL foreign key constraint first (PostgreSQL requires this)
        Schema::table('signatory_registry', function (Blueprint $table) {
            $table->dropForeign(['primary_employee_id']);
        });

        // Re-add it as nullable
        Schema::table('signatory_registry', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_employee_id')->nullable()->change();
            $table->foreign('primary_employee_id')->references('id')->on('employees')->onDelete('set null');
        });

        // Wipe the placeholder assignments set by the seeder so all slots start empty
        DB::table('signatory_registry')->update(['primary_employee_id' => null]);
    }

    public function down(): void
    {
        Schema::table('signatory_registry', function (Blueprint $table) {
            $table->dropForeign(['primary_employee_id']);
        });

        Schema::table('signatory_registry', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_employee_id')->nullable(false)->change();
            $table->foreign('primary_employee_id')->references('id')->on('employees');
        });
    }
};
