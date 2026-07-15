<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('submission_due_date')->constrained('offices')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->after('office_id')->constrained('users')->nullOnDelete();
            $table->string('pdf_attachment_path')->nullable()->after('created_by_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('employee_id')->constrained('offices')->nullOnDelete();
        });

        // Backfill existing data
        // Users
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->employee_id) {
                $employee = DB::table('employees')->where('id', $user->employee_id)->first();
                if ($employee && $employee->office_division) {
                    $office = DB::table('offices')->where('name', $employee->office_division)->first();
                    if ($office) {
                        DB::table('users')->where('id', $user->id)->update(['office_id' => $office->id]);
                    }
                }
            }
        }

        // Procurement folders
        $folders = DB::table('procurement_folders')->get();
        foreach ($folders as $folder) {
            $officeId = null;
            if ($folder->requesting_unit) {
                $office = DB::table('offices')->where('name', $folder->requesting_unit)->first();
                if ($office) {
                    $officeId = $office->id;
                }
            }

            $userId = null;
            if ($folder->requested_by_id) {
                $user = DB::table('users')->where('employee_id', $folder->requested_by_id)->first();
                if ($user) {
                    $userId = $user->id;
                }
            }

            if ($officeId || $userId) {
                DB::table('procurement_folders')->where('id', $folder->id)->update([
                    'office_id' => $officeId,
                    'created_by_id' => $userId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_folders', function (Blueprint $table) {
            $table->dropForeign(['procurement_folders_office_id_foreign']);
            $table->dropForeign(['procurement_folders_created_by_id_foreign']);
            $table->dropColumn(['office_id', 'created_by_id', 'pdf_attachment_path']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['users_office_id_foreign']);
            $table->dropColumn(['office_id']);
        });
    }
};
