<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add signature timestamps to procurement_folders
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->timestamp('requested_signed_at')->nullable()->after('requested_by_designation');
            $table->timestamp('recommended_signed_at')->nullable()->after('recommended_by_designation');
            $table->timestamp('approved_signed_at')->nullable()->after('approved_by_designation');

            // Modify status column from enum to string to support more statuses flexibly
            $table->string('status')->default('DRAFT')->change();
        });

        // 2. Create procurement_logs table
        Schema::create('procurement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('procurement_folder_id')->constrained('procurement_folders')->onDelete('cascade');
            $table->string('action'); // Enum values: 'REJECTED', 'RESUBMITTED', 'APPROVED'
            $table->foreignId('actor_id')->constrained('employees');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_logs');

        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropColumn([
                'requested_signed_at',
                'recommended_signed_at',
                'approved_signed_at',
            ]);
        });
    }
};
