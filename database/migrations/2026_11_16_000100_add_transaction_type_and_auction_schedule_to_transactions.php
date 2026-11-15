<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('transactions', 'transaction_type')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('transaction_type', 24)->default('private_sale')->after('source');
            });
        }

        if (!Schema::hasColumn('transactions', 'auction_schedule_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignUuid('auction_schedule_id')
                    ->nullable()
                    ->after('transaction_type')
                    ->constrained('auction_schedules')
                    ->nullOnDelete();
            });
        }

        DB::table('transactions')->whereNull('transaction_type')->update(['transaction_type' => 'private_sale']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'auction_schedule_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['auction_schedule_id']);
                $table->dropColumn('auction_schedule_id');
            });
        }
        if (Schema::hasColumn('transactions', 'transaction_type')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('transaction_type');
            });
        }
    }
};
