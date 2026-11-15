<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('auction_farmer_follow_ups')) return;

        Schema::create('auction_farmer_follow_ups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auction_registration_id')->unique()->constrained('auction_registrations')->cascadeOnDelete();
            $table->foreignUuid('auction_schedule_id')->constrained('auction_schedules')->restrictOnDelete();
            $table->foreignUuid('livestock_id')->constrained('livestock_records')->restrictOnDelete();
            $table->foreignUuid('farmer_id')->constrained('farmer_profiles')->restrictOnDelete();
            $table->foreignUuid('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->string('status', 24)->default('pending'); // pending, responded, overdue, unresponsive
            $table->string('response', 32)->nullable(); // sold, unsold, withdrawn, not_brought, sold_already_recorded
            $table->text('response_notes')->nullable();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('auction_completed_at');
            $table->timestamp('first_notified_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->timestamp('unresponsive_at')->nullable();
            $table->timestamps();
            $table->index(['farmer_id', 'status', 'created_at'], 'auction_followup_farmer_status_idx');
            $table->index(['municipality_id', 'status', 'created_at'], 'auction_followup_muni_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_farmer_follow_ups');
    }
};
