<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('transactions', 'selling_location')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('selling_location', 255)->nullable();
            });
        }

        if (!Schema::hasColumn('transactions', 'selling_barangay_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignUuid('selling_barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('transactions', 'transaction_type')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('transaction_type', 24)->default('private');
            });
        }

        if (!Schema::hasColumn('transactions', 'auction_schedule_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignUuid('auction_schedule_id')->nullable()->constrained('auction_schedules')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally retain transaction metadata to avoid deleting recorded auction history.
    }
};
