<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_items', function (Blueprint $table) {
            $table->string('unit')->default('pcs')->after('total_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
