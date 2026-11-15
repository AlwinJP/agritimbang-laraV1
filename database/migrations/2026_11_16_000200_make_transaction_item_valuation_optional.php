<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('transaction_items', 'valuation_id')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['transaction_items', 'valuation_id']
        );

        if ($column && strtoupper((string) $column->IS_NULLABLE) === 'NO') {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->dropForeign(['valuation_id']);
            });
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->foreignUuid('valuation_id')->nullable()->change();
                $table->foreign('valuation_id')->references('id')->on('valuations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('transaction_items', 'valuation_id')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['transaction_items', 'valuation_id']
        );

        if ($column && strtoupper((string) $column->IS_NULLABLE) === 'YES') {
            // A non-nullable rollback is safe only when every row has a valuation.
            $missing = DB::table('transaction_items')->whereNull('valuation_id')->exists();
            if ($missing) {
                throw new RuntimeException('Cannot make transaction_items.valuation_id required while transaction items without valuations exist.');
            }
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->dropForeign(['valuation_id']);
            });
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->foreignUuid('valuation_id')->nullable(false)->change();
                $table->foreign('valuation_id')->references('id')->on('valuations');
            });
        }
    }
};
