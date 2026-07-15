<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_attachments', function (Blueprint $table) {
            $table->id();
            // Since procurement_folders table uses UUID, foreignId should use a string/uuid morph or explicit type
            $table->uuid('procurement_folder_id');
            $table->foreign('procurement_folder_id')->references('id')->on('procurement_folders')->onDelete('cascade');

            // Classification Categories: 'SYSTEM_PR', 'SYSTEM_RFQ', 'SYSTEM_ABC', 'USER_OTHER'
            $table->string('attachment_type');

            // Storage Context
            $table->string('file_path');     // relative path inside the disk root
            $table->string('original_name');  // e.g., "technical_drawing_v3.pdf"
            $table->string('mime_type');      // e.g., "application/pdf"
            $table->bigInteger('file_size');  // stored in bytes

            $table->foreignId('uploaded_by_employee_id')->constrained('employees');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_attachments');
    }
};
