<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep auction lifecycle statuses synchronized automatically.
Schedule::command('auctions:sync-statuses')->everyMinute()->withoutOverlapping();

// Auction farmer follow-ups: first notice on day 1, reminders on days 3 and 7,
// then overdue/unresponsive monitoring without indefinite reminders.
Schedule::command('auctions:process-farmer-follow-ups')->dailyAt('09:00')->timezone('Asia/Manila')->withoutOverlapping();
