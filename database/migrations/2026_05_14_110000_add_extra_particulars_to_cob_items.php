<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_items', function (Blueprint $table) {
            $table->text('particulars1')->nullable();
            $table->text('particulars2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cob_items', function (Blueprint $table) {
            $table->dropColumn(['particulars1', 'particulars2']);
        });
    }
};
