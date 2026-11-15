<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->change();
            $table->string('transaction_type', 20)->default('private')->after('transaction_date');
            $table->string('selling_location', 255)->nullable()->after('transaction_type');
            $table->foreignUuid('selling_barangay_id')->nullable()->after('selling_location')->constrained('barangays')->nullOnDelete();
            $table->foreignUuid('auction_schedule_id')->nullable()->after('selling_barangay_id')->constrained('auction_schedules')->restrictOnDelete();
            $table->index(['transaction_type', 'transaction_date'], 'transactions_type_date_idx');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->string('sale_purpose', 20)->default('slaughter')->after('valuation_id');
        });

        // Preserve historical meaning where it matches the supported sale purposes.
        DB::statement("UPDATE transaction_items ti JOIN livestock_records lr ON lr.id = ti.livestock_id SET ti.sale_purpose = CASE WHEN lr.sale_purpose IN ('slaughter','breeding') THEN lr.sale_purpose ELSE 'slaughter' END");
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('sale_purpose');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_type_date_idx');
            $table->dropConstrainedForeignId('auction_schedule_id');
            $table->dropConstrainedForeignId('selling_barangay_id');
            $table->dropColumn(['transaction_type', 'selling_location']);
            $table->string('buyer_name')->nullable(false)->change();
        });
    }
};
