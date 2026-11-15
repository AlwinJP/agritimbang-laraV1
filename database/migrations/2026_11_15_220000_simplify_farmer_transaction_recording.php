<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Buyer details are optional for farmer and encoder-recorded transactions.
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->change();
        });

        // The earlier transaction-recording migration (130000) already creates
        // sale_purpose as a string with a 'slaughter' default. Only add it here
        // for installations where that earlier migration did not create it.
        if (!Schema::hasColumn('transaction_items', 'sale_purpose')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->string('sale_purpose', 20)
                    ->default('slaughter')
                    ->after('livestock_id');
            });
        }
    }

    public function down(): void
    {
        // Do not drop sale_purpose here: it may have been created by the
        // earlier 130000 migration and may contain transaction data.
        // buyer_name intentionally remains nullable so optional buyer details
        // continue to work after rollback.
    }
};
