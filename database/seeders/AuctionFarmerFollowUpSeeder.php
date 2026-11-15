<?php

namespace Database\Seeders;

use App\Models\AuctionFarmerFollowUp;
use App\Models\AuctionHouse;
use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Species;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates clearly named, repeatable sample auction follow-ups for local testing.
 * Run DemoUsersSeeder and the municipality/species seeders first.
 */
class AuctionFarmerFollowUpSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('AuctionFarmerFollowUpSeeder is disabled in production.');
        }

        $municipality = Municipality::query()
            ->where('name', 'Barili')
            ->where('province', 'Cebu')
            ->first();

        if (!$municipality) {
            throw new RuntimeException('Barili, Cebu was not found. Run BariliMunicipalitySeeder first.');
        }

        $farmerUser = User::query()->where('email', 'farmer1@gmail.com')->first();
        $farmer = $farmerUser
            ? FarmerProfile::query()->where('user_id', $farmerUser->id)->first()
            : null;

        if (!$farmer || !$farmerUser) {
            throw new RuntimeException('Demo farmer1 profile was not found. Run DemoUsersSeeder first.');
        }

        $authority = User::query()->where('email', 'authority@gmail.com')->first() ?? $farmerUser;

        $barangay = $municipality->barangays()->orderBy('name')->first();
        if (!$barangay) {
            throw new RuntimeException('Barili has no barangays. Run the municipality/barangay seeder first.');
        }

        $house = AuctionHouse::query()->firstOrCreate(
            ['municipality_id' => $municipality->id, 'name' => 'DEMO Auction House - Follow-up Testing'],
            [
                'barangay_id' => $barangay->id,
                'address' => 'Synthetic test venue, Barili, Cebu',
                'is_active' => true,
            ]
        );

        $speciesNames = ['Carabao', 'Cattle', 'Goat', 'Hog'];
        $species = collect($speciesNames)
            ->map(fn (string $name) => Species::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first())
            ->filter()
            ->values();
        if ($species->isEmpty()) {
            $species = Species::query()->orderBy('name')->take(4)->get();
        }
        if ($species->isEmpty()) {
            throw new RuntimeException('No livestock species found. Run SpeciesSeeder first.');
        }

        // Day 0/1/3/7 test the response and reminder stages; day 14 tests overdue;
        // day 30 tests unresponsive. A final responded record tests read-only history.
        $cases = [
            ['key' => 'day-0', 'days' => 0, 'status' => 'pending', 'reminders' => 0],
            ['key' => 'day-1', 'days' => 1, 'status' => 'pending', 'reminders' => 0],
            ['key' => 'day-3', 'days' => 3, 'status' => 'pending', 'reminders' => 1],
            ['key' => 'day-7', 'days' => 7, 'status' => 'pending', 'reminders' => 2],
            ['key' => 'day-14', 'days' => 14, 'status' => 'overdue', 'reminders' => 3],
            ['key' => 'day-30', 'days' => 30, 'status' => 'unresponsive', 'reminders' => 3],
            ['key' => 'responded', 'days' => 5, 'status' => 'responded', 'reminders' => 1, 'response' => 'unsold'],
        ];

        DB::transaction(function () use ($cases, $farmer, $farmerUser, $municipality, $house, $authority, $barangay, $species) {
            foreach ($cases as $index => $case) {
                $animalSpecies = $species[$index % $species->count()];
                $auctionDate = Carbon::now('Asia/Manila')->subDays($case['days'])->toDateString();
                $title = 'DEMO FOLLOW-UP ' . strtoupper($case['key']) . ' - ' . $auctionDate;

                $schedule = AuctionSchedule::query()->firstOrCreate(
                    ['municipality_id' => $municipality->id, 'title' => $title],
                    [
                        'auction_house_id' => $house->id,
                        'barangay_id' => $barangay->id,
                        'created_by' => $authority?->id ?? $farmerUser->id,
                        'auction_date' => $auctionDate,
                        'start_time' => '08:00:00',
                        'expected_end_time' => '12:00:00',
                        'venue' => 'DEMO Auction House - Follow-up Testing, Barili, Cebu',
                        'registration_opens_at' => Carbon::parse($auctionDate . ' 08:00:00', 'Asia/Manila')->subDays(7)->utc(),
                        'registration_closes_at' => Carbon::parse($auctionDate . ' 17:00:00', 'Asia/Manila')->subDay()->utc(),
                        'description' => 'Synthetic auction event created only for Farmer Follow-up testing.',
                        'status' => 'completed',
                        'published_at' => Carbon::parse($auctionDate . ' 07:00:00', 'Asia/Manila')->utc(),
                    ]
                );

                $registration = AuctionRegistration::query()
                    ->where('auction_schedule_id', $schedule->id)
                    ->first();

                if (!$registration) {
                    $livestock = LivestockRecord::query()->create([
                        'farmer_id' => $farmer->id,
                        'species_id' => $animalSpecies->id,
                        'breed_id' => null,
                        'sex' => $index % 2 === 0 ? 'female' : 'male',
                        'age_months' => 24 + ($index * 3),
                        'age_group' => 'adult',
                        'reproductive_status' => 'unknown',
                        'sale_purpose' => 'slaughter',
                        'condition_score' => 3,
                        'actual_weight_kg' => null,
                        'municipality_id' => $municipality->id,
                        'barangay_id' => $farmer->barangay_id ?: $barangay->id,
                        'status' => 'available',
                        'created_by' => $farmerUser->id,
                    ]);

                    $registration = AuctionRegistration::query()->create([
                        'auction_schedule_id' => $schedule->id,
                        'livestock_id' => $livestock->id,
                        'farmer_id' => $farmer->id,
                        'registered_by' => $farmerUser->id,
                        'active_livestock_id' => null,
                        'status' => 'completed',
                        'registered_at' => Carbon::parse($auctionDate . ' 07:30:00', 'Asia/Manila')->utc(),
                        'remarks' => 'DEMO FOLLOW-UP TEST DATA. Not a real livestock auction record.',
                    ]);
                }

                $completedAt = Carbon::parse($auctionDate . ' 12:00:00', 'Asia/Manila')->utc();
                $reminderCount = (int) $case['reminders'];
                $responded = $case['status'] === 'responded';

                AuctionFarmerFollowUp::query()->firstOrCreate(
                    ['auction_registration_id' => $registration->id],
                    [
                        'auction_schedule_id' => $schedule->id,
                        'livestock_id' => $registration->livestock_id,
                        'farmer_id' => $registration->farmer_id,
                        'municipality_id' => $municipality->id,
                        'status' => $case['status'],
                        'response' => $case['response'] ?? null,
                        'response_notes' => $responded ? 'DEMO response: livestock remains available.' : null,
                        'transaction_id' => null,
                        'auction_completed_at' => $completedAt,
                        'first_notified_at' => $reminderCount > 0 ? $completedAt->copy()->addDay() : null,
                        'last_notified_at' => $reminderCount > 0 ? $completedAt->copy()->addDays(min(7, $reminderCount * 2 + 1)) : null,
                        'reminder_count' => $reminderCount,
                        'responded_at' => $responded ? now('UTC') : null,
                        'overdue_at' => $case['status'] === 'overdue' ? now('UTC') : null,
                        'unresponsive_at' => $case['status'] === 'unresponsive' ? now('UTC') : null,
                    ]
                );
            }
        });

        $this->command?->info('Seeded 7 synthetic Farmer Follow-up records (day 0, 1, 3, 7, 14, 30, and responded).');
        $this->command?->warn('Test data only. Demo farmer accounts must be seeded first.');
    }
}
