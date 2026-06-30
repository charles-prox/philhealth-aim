<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            // Drop old code column if it exists
            if (Schema::hasColumn('offices', 'code')) {
                $table->dropColumn('code');
            }

            $table->enum('type', ['DIVISION', 'SECTION', 'UNIT'])->default('UNIT')->after('name');
            $table->string('acronym', 15)->unique()->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('acronym');
            $table->string('code')->nullable()->after('name');
        });
    }
};
