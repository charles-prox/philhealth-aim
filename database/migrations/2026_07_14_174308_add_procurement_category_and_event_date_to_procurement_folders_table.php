<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            // Core Category Code String Tag
            $table->string('procurement_category')->after('tracking_number');

            // Critical Timeline Target Anchor
            $table->date('event_date')->nullable()->after('procurement_category');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropColumn(['procurement_category', 'event_date']);
        });
    }
};
