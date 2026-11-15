<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('transactions', 'selling_barangay_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignUuid('selling_barangay_id')
                    ->nullable()
                    ->after('barangay_id')
                    ->constrained('barangays');
            });
        }

        if (Schema::hasColumn('transactions', 'selling_location')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('selling_location');
            });
        }

        DB::table('transactions')
            ->whereNull('selling_barangay_id')
            ->update(['selling_barangay_id' => DB::raw('barangay_id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'selling_barangay_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('selling_barangay_id');
            });
        }
    }
};
