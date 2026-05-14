<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_versions', function (Blueprint $table) {
            $table->text('remarks')->nullable();
        });

        Schema::table('cob_items', function (Blueprint $table) {
            $table->text('revision_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cob_versions', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });

        Schema::table('cob_items', function (Blueprint $table) {
            $table->dropColumn('revision_remarks');
        });
    }
};
