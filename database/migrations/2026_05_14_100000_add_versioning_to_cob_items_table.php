<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_versions', function (Blueprint $table) {
            $table->enum('status', ['DRAFT', 'APPROVED', 'SUPERSEDED'])->default('DRAFT');
        });

        Schema::table('cob_items', function (Blueprint $table) {
            $table->integer('version_number')->default(1);
            $table->boolean('is_active')->default(false); // Only true when approved
            $table->enum('status', ['DRAFT', 'APPROVED', 'SUPERSEDED', 'CANCELLED'])->default('DRAFT');
            $table->foreignUuid('superseded_by_id')->nullable()->constrained('cob_items');
        });
    }

    public function down(): void
    {
        Schema::table('cob_items', function (Blueprint $table) {
            $table->dropForeign(['superseded_by_id']);
            $table->dropColumn(['version_number', 'is_active', 'status', 'superseded_by_id']);
        });

        Schema::table('cob_versions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
