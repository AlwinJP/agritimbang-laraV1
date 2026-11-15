<?php

namespace App\Console\Commands;

use App\Models\AuctionFarmerFollowUp;
use App\Models\AuctionRegistration;
use App\Models\TransactionItem;
use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessAuctionFarmerFollowUps extends Command
{
    protected $signature = 'auctions:process-farmer-follow-ups';
    protected $description = 'Create auction farmer follow-ups and send scheduled reminders.';

    public function handle(): int
    {
        $this->createMissingFollowUps();
        $this->updateAgingStatuses();
        $this->sendDueReminders();
        return self::SUCCESS;
    }

    private function createMissingFollowUps(): void
    {
        AuctionRegistration::query()
            ->with(['schedule', 'livestock'])
            ->where('status', 'completed')
            ->whereHas('schedule', fn ($q) => $q->where('status', 'completed'))
            ->orderBy('created_at')
            ->chunkById(100, function ($registrations) {
                foreach ($registrations as $registration) {
                    if (!$registration->schedule || !$registration->livestock) continue;
                    if ($registration->livestock->status !== 'available') continue;
                    if (AuctionFarmerFollowUp::where('auction_registration_id', $registration->id)->exists()) continue;

                    // Any submitted, verified, or rejected transaction already has a review trail;
                    // do not ask the farmer to create a second transaction for the same animal.
                    $hasRecordedSale = TransactionItem::query()
                        ->where('livestock_id', $registration->livestock_id)
                        ->whereHas('transaction', fn ($q) => $q->whereIn('status', ['pending', 'pending_lgu_verification', 'verified', 'rejected']))
                        ->exists();
                    if ($hasRecordedSale) continue;

                    $schedule = $registration->schedule;
                    $completedAt = Carbon::parse(
                        $schedule->auction_date->toDateString().' '.($schedule->expected_end_time ?: $schedule->start_time),
                        'Asia/Manila'
                    )->utc();

                    AuctionFarmerFollowUp::create([
                        'auction_registration_id' => $registration->id,
                        'auction_schedule_id' => $schedule->id,
                        'livestock_id' => $registration->livestock_id,
                        'farmer_id' => $registration->farmer_id,
                        'municipality_id' => $schedule->municipality_id,
                        'status' => 'pending',
                        'auction_completed_at' => $completedAt,
                    ]);
                }
            });
    }

    private function sendDueReminders(): void
    {
        AuctionFarmerFollowUp::query()
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNull('responded_at')
            ->with(['farmer.user', 'livestock.species', 'schedule'])
            ->chunkById(100, function ($followUps) {
                foreach ($followUps as $followUp) {
                    $ageDays = $followUp->auction_completed_at->diffInDays(now('UTC'));
                    $due = false;
                    if ($followUp->reminder_count === 0 && $ageDays >= 1) $due = true;
                    elseif ($followUp->reminder_count === 1 && $ageDays >= 3) $due = true;
                    elseif ($followUp->reminder_count === 2 && $ageDays >= 7) $due = true;
                    if (!$due || !$followUp->farmer?->user) continue;

                    [$event, $title, $message] = match ($followUp->reminder_count) {
                        0 => ['auction.follow_up.initial', 'Please update your livestock status', 'Please tell us what happened to your '.($followUp->livestock?->species?->name ?? 'livestock').' after '.$followUp->schedule?->title.'.'],
                        1 => ['auction.follow_up.reminder_3d', 'Auction follow-up reminder', 'Your livestock auction follow-up is still waiting for your response. Please update its status.'],
                        default => ['auction.follow_up.reminder_7d', 'Final auction follow-up reminder', 'We still need your livestock status update. You can respond through your AgriTimbang account or ask the MAO for assistance.'],
                    };

                    $followUp->farmer->user->notify(new SystemAlertNotification(
                        $event, $title, $message, (string) $followUp->municipality_id,
                        'auction_follow_up', (string) $followUp->id, false, '/auction-follow-ups?follow_up=' . $followUp->id
                    ));

                    $followUp->update([
                        'first_notified_at' => $followUp->first_notified_at ?? now('UTC'),
                        'last_notified_at' => now('UTC'),
                        'reminder_count' => $followUp->reminder_count + 1,
                    ]);
                }
            });
    }

    private function updateAgingStatuses(): void
    {
        AuctionFarmerFollowUp::query()->whereNull('responded_at')->where('status', '!=', 'unresponsive')
            ->where('auction_completed_at', '<=', now('UTC')->subDays(30))
            ->update(['status' => 'unresponsive', 'unresponsive_at' => now('UTC'), 'updated_at' => now('UTC')]);

        AuctionFarmerFollowUp::query()->whereNull('responded_at')->where('status', 'pending')
            ->where('auction_completed_at', '<=', now('UTC')->subDays(14))
            ->update(['status' => 'overdue', 'overdue_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }
}
