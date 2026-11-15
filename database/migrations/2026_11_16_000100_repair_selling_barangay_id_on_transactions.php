<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Repair migration: the earlier location migration may have been
        // recorded as completed on installations where the column was absent.
        if (!Schema::hasColumn('transactions', 'selling_barangay_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->uuid('selling_barangay_id')->nullable()->after('barangay_id');
            });
        }

        // Add the foreign key only when the column exists and no matching
        // constraint has already been installed by an earlier migration.
        $hasConstraint = collect(DB::select("\n            SELECT CONSTRAINT_NAME\n            FROM information_schema.KEY_COLUMN_USAGE\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'transactions'\n              AND COLUMN_NAME = 'selling_barangay_id'\n              AND REFERENCED_TABLE_NAME = 'barangays'\n        "))->isNotEmpty();

        if (!$hasConstraint) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('selling_barangay_id', 'transactions_selling_barangay_fk')
                    ->references('id')->on('barangays')->nullOnDelete();
            });
        }

        // Existing records inherit the recorded farmer barangay as their
        // initial selling barangay. New forms can select a different one.
        DB::table('transactions')
            ->whereNull('selling_barangay_id')
            ->update(['selling_barangay_id' => DB::raw('barangay_id')]);
    }

    public function down(): void
    {
        // Intentionally retain this operational column to avoid data loss
        // when rolling back a repair migration.
    }
};
