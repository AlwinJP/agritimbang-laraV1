<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionFarmerFollowUp extends Model
{
    use HasUuids;

    protected $fillable = [
        'auction_registration_id', 'auction_schedule_id', 'livestock_id', 'farmer_id', 'municipality_id',
        'status', 'response', 'response_notes', 'transaction_id', 'auction_completed_at',
        'first_notified_at', 'last_notified_at', 'reminder_count', 'responded_at', 'overdue_at', 'unresponsive_at',
    ];

    protected function casts(): array
    {
        return [
            'auction_completed_at' => 'datetime', 'first_notified_at' => 'datetime',
            'last_notified_at' => 'datetime', 'responded_at' => 'datetime',
            'overdue_at' => 'datetime', 'unresponsive_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }

    public function registration(): BelongsTo { return $this->belongsTo(AuctionRegistration::class, 'auction_registration_id'); }
    public function schedule(): BelongsTo { return $this->belongsTo(AuctionSchedule::class, 'auction_schedule_id'); }
    public function livestock(): BelongsTo { return $this->belongsTo(LivestockRecord::class, 'livestock_id'); }
    public function farmer(): BelongsTo { return $this->belongsTo(FarmerProfile::class, 'farmer_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
