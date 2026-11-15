<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncoderTransactionRequest;
use App\Models\FarmerProfile;
use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\LivestockRecord;
use App\Models\Transaction;
use App\Services\BuyerConfirmationService;
use App\Services\TransactionService;
use App\Services\LivestockService;
use App\Services\ValuationService;
use App\Models\Species;
use App\Models\Breed;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LguTransactionEncodingController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly BuyerConfirmationService $confirmationService,
        private readonly LivestockService $livestockService,
        private readonly ValuationService $valuationService
    ) {
    }

    private function municipalityId(Request $request): string
    {
        $user = $request->user();

        abort_unless(
            $user->roles()
                ->where('slug', 'lgu_encoder')
                ->exists(),
            403
        );

        $municipalityId =
            $user->lguOfficerProfile?->municipality_id;

        abort_unless(
            $municipalityId,
            403,
            'No municipality assigned to this encoder.'
        );

        return (string) $municipalityId;
    }

    public function farmers(Request $request): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim($data['search'] ?? '');

        $farmers = FarmerProfile::query()
            ->with([
                'user:id,first_name,last_name,verification_status',
                'barangay:id,name',
            ])
            ->where('municipality_id', $municipalityId)
            ->whereHas('user', function ($query) use ($search) {
                $query->where('verification_status', 'verified');

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('verification_status', 'verified')
                            ->where(function ($nameQuery) use ($search) {
                                $nameQuery->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    })->orWhere('farmer_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('farmer_code')
            ->paginate($data['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'data' => $farmers,
        ]);
    }

    public function livestock(
        Request $request,
        FarmerProfile $farmer
    ): JsonResponse {
        $municipalityId = $this->municipalityId($request);

        abort_unless(
            (string) $farmer->municipality_id === $municipalityId,
            403
        );

        abort_unless(
            $farmer->user?->verification_status === 'verified',
            422,
            'The selected farmer is not verified.'
        );

        $auctionScheduleId = $request->query('auction_schedule_id');
        if ($auctionScheduleId) {
            $schedule = \App\Models\AuctionSchedule::query()
                ->whereKey($auctionScheduleId)
                ->where('municipality_id', $municipalityId)
                ->firstOrFail();
        }

        $livestock = LivestockRecord::query()
            ->with([
                'species',
                'breed',
                'valuations' => fn($query) =>
                    $query->latest('created_at'),
                'activeAuctionRegistration.schedule',
            ])
            ->where('farmer_id', $farmer->id)
            ->where('municipality_id', $municipalityId)
            ->when(!$auctionScheduleId, fn ($query) => $query->where('status', 'available'))
            ->when($auctionScheduleId, fn ($query) => $query->whereHas('auctionRegistrations', function ($registration) use ($auctionScheduleId) {
                $registration->where('auction_schedule_id', $auctionScheduleId)
                    ->whereIn('status', ['registered', 'completed', 'pending_sale_review']);
            }))
            ->withExists(['transactionItems as has_active_transaction' => function ($items) {
                $items->whereHas('transaction', fn ($transaction) => $transaction->whereIn('status', ['pending', 'pending_lgu_verification', 'verified']));
            }])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (LivestockRecord $animal) {
                $animal->setAttribute('selectable', $animal->status === 'available' && !$animal->has_active_transaction);
                $animal->setAttribute('selection_block_reason', $animal->status !== 'available'
                    ? 'This livestock is not currently available.'
                    : ($animal->has_active_transaction ? 'This livestock already has a pending or verified transaction.' : null));
                $animal->setAttribute('auction_participation_status', $animal->activeAuctionRegistration?->status);
                return $animal;
            });

        return response()->json([
            'success' => true,
            'data' => $livestock,
        ]);
    }

    public function transactionAuctionSchedules(Request $request): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);
        app(\App\Services\AuctionScheduleService::class)->syncAutomaticStatuses($municipalityId);
        $schedules = \App\Models\AuctionSchedule::query()
            ->where('municipality_id', $municipalityId)
            ->whereIn('status', ['ongoing', 'completed'])
            ->with(['auctionHouse', 'barangay'])
            ->orderByDesc('auction_date')->orderByDesc('start_time')->limit(100)->get();
        return response()->json(['success' => true, 'data' => $schedules]);
    }

    public function quickAddLivestock(Request $request, FarmerProfile $farmer): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);
        abort_unless((string) $farmer->municipality_id === $municipalityId, 404);
        abort_unless($farmer->user?->verification_status === 'verified', 422, 'The selected farmer is not verified.');

        $data = $request->validate([
            'auction_schedule_id' => ['required', 'uuid', 'exists:auction_schedules,id'],
            'species_id' => ['required', 'uuid', 'exists:species,id'],
            'breed_id' => ['nullable', 'uuid', 'exists:breeds,id'],
            'sex' => ['required', 'in:male,female,unknown'],
            'age_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'actual_weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'sale_purpose' => ['required', 'in:slaughter,breeding'],
        ]);

        $schedule = AuctionSchedule::query()
            ->whereKey($data['auction_schedule_id'])
            ->where('municipality_id', $municipalityId)
            ->whereIn('status', ['ongoing', 'completed'])
            ->firstOrFail();

        $species = Species::query()->whereKey($data['species_id'])->where('is_active', true)->firstOrFail();
        if (!empty($data['breed_id']) && !Breed::query()->whereKey($data['breed_id'])->where('species_id', $species->id)->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Choose a breed belonging to the selected species.'], 422);
        }

        $animal = DB::transaction(function () use ($request, $farmer, $data, $schedule) {
            $record = $this->livestockService->createForLgu($request->user(), $farmer, array_merge($data, [
                'condition_score' => null,
                'reproductive_status' => 'unknown',
                'parity' => null,
            ]));

            $this->valuationService->calculate($record, $request->user());

            // Manual auction-assist creation links the animal to this actual
            // event even when the farmer did not pre-register it. It is held
            // for Authority review rather than pretending it was pre-approved.
            AuctionRegistration::create([
                'auction_schedule_id' => $schedule->id,
                'livestock_id' => $record->id,
                'farmer_id' => $farmer->id,
                'registered_by' => $request->user()->id,
                'active_livestock_id' => $record->id,
                'status' => 'pending_sale_review',
                'registered_at' => now(),
                'remarks' => 'Added by LGU Encoder while recording an actual sale for this auction event; awaiting Authority transaction review.',
            ]);

            return $record->fresh()->load([
                'species', 'breed',
                'valuations' => fn ($q) => $q->latest('created_at'),
                'activeAuctionRegistration.schedule',
            ]);
        });

        $animal->setAttribute('selectable', true);
        $animal->setAttribute('auction_participation_status', 'pending_sale_review');

        return response()->json([
            'success' => true,
            'message' => 'Livestock created and linked to the selected auction event for Authority review.',
            'data' => $animal,
        ], 201);
    }

    /**
     * Transaction history for the authenticated encoder. Records are limited
     * to transactions encoded by this user and to the user's assigned
     * municipality; this includes drafts and submitted/verified records.
     */
    public function history(Request $request): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);

        $validated = $request->validate([
            'status' => ['nullable', 'in:draft,pending,pending_lgu_verification,verified,rejected,cancelled,disputed'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'species' => ['nullable', 'uuid'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $dateFrom = $validated['date_from'] ?? $validated['from'] ?? null;
        $dateTo = $validated['date_to'] ?? $validated['to'] ?? null;

        $transactions = Transaction::query()
            ->with([
                'farmer.user:id,first_name,middle_name,last_name,phone_number',
                'municipality:id,name',
                'barangay:id,name',
                'items.livestock.species:id,name',
                'items.livestock.breed:id,name',
                'items.valuation',
                'auctionSchedule.auctionHouse',
                'auctionSchedule.barangay',
                'encoder:id,first_name,middle_name,last_name',
                'verifier:id,first_name,middle_name,last_name',
                'statusLogs.actor:id,first_name,middle_name,last_name',
            ])
            ->withCount('items')
            // Encoder history is the transaction history for the assigned
            // municipality, including farmer-submitted and encoder-assisted rows.
            // Do not filter by encoded_by: that would hide farmer submissions.
            ->where('municipality_id', $municipalityId)
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->when(!empty($validated['species']) || !empty($validated['purpose']), function ($query) use ($validated) {
                $query->whereHas('items.livestock', function ($livestock) use ($validated) {
                    if (!empty($validated['species'])) $livestock->where('species_id', $validated['species']);
                    if (!empty($validated['purpose'])) $livestock->where('sale_purpose', strtolower($validated['purpose']));
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('id', 'like', "%{$search}%")
                        ->orWhere('buyer_name', 'like', "%{$search}%")
                        ->orWhere('buyer_phone', 'like', "%{$search}%")
                        ->orWhereHas('farmer.user', function ($userQuery) use ($search) {
                            $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'species' => \App\Models\Species::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreEncoderTransactionRequest $request): JsonResponse
    {
        $municipalityId = $this->municipalityId($request);
        $data = $request->validated();

        abort_unless(($data['transaction_type'] ?? null) === 'auction', 422, 'Encoder transaction recording is limited to auction sales.');

        $schedule = AuctionSchedule::query()
            ->whereKey($data['auction_schedule_id'])
            ->where('municipality_id', $municipalityId)
            ->whereIn('status', ['ongoing', 'completed'])
            ->firstOrFail();

        foreach ($data['items'] as $index => $item) {
            $linked = AuctionRegistration::query()
                ->where('auction_schedule_id', $schedule->id)
                ->where('livestock_id', $item['livestock_id'])
                ->where('farmer_id', $data['farmer_id'])
                ->whereIn('status', ['registered', 'completed', 'pending_sale_review'])
                ->exists();

            if (!$linked) {
                return response()->json([
                    'message' => 'Every livestock item in an auction sale must be linked to the selected auction event. Use Add New Livestock to record an animal that was sold at this event but was not pre-registered.',
                    'errors' => ["items.{$index}.livestock_id" => ['This livestock is not linked to the selected auction event.']],
                ], 422);
            }
        }

        // Use the event's venue details as the authoritative sale location.
        $data['selling_location'] = $data['selling_location'] ?: ($schedule->venue ?: $schedule->auctionHouse?->name ?: 'Auction venue');
        $data['selling_barangay_id'] = $data['selling_barangay_id'] ?: ($schedule->barangay_id ?: $schedule->auctionHouse?->barangay_id);

        $transaction = $this->transactionService->createEncoderDraft($request->user(), $data);

        return response()->json([
            'success' => true,
            'message' => 'Auction transaction draft encoded successfully.',
            'data' => $transaction,
        ], 201);
    }

    public function submit(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->municipalityId($request);

        $sale = $this->transactionService
            ->submitEncoderDraft(
                $request->user(),
                $transaction->id,
                $this->confirmationService
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Transaction submitted for LGU verification.',
            'data' => $sale,
        ]);
    }
}
