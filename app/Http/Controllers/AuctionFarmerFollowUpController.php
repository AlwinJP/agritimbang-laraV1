<?php

namespace App\Http\Controllers;

use App\Models\AuctionFarmerFollowUp;
use App\Models\FarmerProfile;
use App\Models\TransactionItem;
use App\Models\Barangay;
use App\Models\User;
use App\Services\TransactionService;
use App\Services\BuyerConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionFarmerFollowUpController extends Controller
{
    public function farmerIndex(Request $request): JsonResponse
    {
        $farmer = FarmerProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
        $query = AuctionFarmerFollowUp::query()->where('farmer_id', $farmer->id)
            ->with(['schedule:id,title,auction_date,start_time,expected_end_time,venue,status', 'livestock.species:id,name', 'livestock.breed:id,name', 'transaction:id,status,total_selling_price'])
            ->when($request->filled('follow_up'), fn ($q) => $q->whereKey($request->input('follow_up')));
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        $summary = AuctionFarmerFollowUp::query()->where('farmer_id', $farmer->id)->selectRaw("SUM(status = 'pending') as pending, SUM(status = 'overdue') as overdue, SUM(status = 'unresponsive') as unresponsive, SUM(status = 'responded') as responded")->first();
        return response()->json(['summary' => $summary, 'data' => $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'overdue' THEN 1 WHEN 'unresponsive' THEN 2 ELSE 3 END")->orderByDesc('auction_completed_at')->paginate(min(50, max(1, $request->integer('per_page', 12))))]);
    }

    public function respond(Request $request, string $followUp, TransactionService $transactions, BuyerConfirmationService $buyerConfirmation): JsonResponse
    {
        $data = $request->validate([
            'response' => ['required', 'in:sold,sold_at_auction,sold_privately,unsold,not_brought'],
            'transaction_date' => ['required_if:response,sold,sold_at_auction,sold_privately', 'nullable', 'date_format:Y-m-d', function ($attribute, $value, $fail) { if ($value && $value > now('Asia/Manila')->toDateString()) $fail('Sale date cannot be in the future.'); }],
            'actual_selling_price' => ['required_if:response,sold,sold_at_auction,sold_privately', 'nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'sale_purpose' => ['required_if:response,sold,sold_at_auction,sold_privately', 'nullable', 'in:slaughter,breeding'],
            'selling_barangay_id' => ['required_if:response,sold_privately', 'nullable', 'uuid', 'exists:barangays,id'],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', \App\Support\ContactValidationRules::philippineMobile(), 'max:13'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $farmer = FarmerProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
        $result = DB::transaction(function () use ($request, $followUp, $farmer, $data, $transactions, $buyerConfirmation) {
            $record = AuctionFarmerFollowUp::query()->whereKey($followUp)->where('farmer_id', $farmer->id)
                ->lockForUpdate()->with(['schedule', 'livestock', 'registration'])->firstOrFail();
            if ($record->responded_at) throw ValidationException::withMessages(['response' => 'You have already responded to this follow-up. Contact the MAO if you need to correct your response.']);

            $transaction = null;
            $responseValue = $data['response'];
            if (in_array($responseValue, ['sold', 'sold_at_auction', 'sold_privately'], true)) {
                $existing = TransactionItem::query()->where('livestock_id', $record->livestock_id)
                    ->whereHas('transaction', fn ($q) => $q->whereIn('status', ['pending', 'pending_lgu_verification', 'verified', 'rejected']))
                    ->with('transaction')->first();

                if ($existing) {
                    $transaction = $existing->transaction;
                    $responseValue = 'sold_already_recorded';
                } else {
                    $schedule = $record->schedule;
                    $isPrivateSale = $responseValue === 'sold_privately';
                    $sellingBarangay = $isPrivateSale
                        ? Barangay::query()->findOrFail($data['selling_barangay_id'])
                        : null;
                    $draft = $transactions->createDraft($request->user(), [
                        'buyer_name' => $data['buyer_name'] ?? null,
                        'buyer_phone' => $data['buyer_phone'] ?? null,
                        'transaction_date' => $data['transaction_date'],
                        'selling_location' => $isPrivateSale ? $sellingBarangay->name : $schedule->venue,
                        'selling_barangay_id' => $isPrivateSale ? $sellingBarangay->id : $schedule->barangay_id,
                        'transaction_type' => $isPrivateSale ? 'private_sale' : 'auction_sale',
                        'auction_schedule_id' => $isPrivateSale ? null : $schedule->id,
                        'remarks' => $isPrivateSale
                            ? 'Private sale reported by farmer through post-auction follow-up.'
                            : 'Auction sale reported by farmer through post-auction follow-up.',
                        'items' => [[
                            'livestock_id' => $record->livestock_id,
                            'sale_purpose' => $data['sale_purpose'],
                            'actual_selling_price' => $data['actual_selling_price'],
                        ]],
                    ]);
                    $transaction = $transactions->submit($request->user(), $draft->id, $buyerConfirmation);
                    // Auction sale submission reserves this event registration in TransactionService.
                    // A privately sold animal is not re-attached to the completed auction.
                }
            }

            $record->update([
                'status' => 'responded',
                'response' => $responseValue,
                'response_notes' => $data['notes'] ?? null,
                'transaction_id' => $transaction?->id,
                'responded_at' => now('UTC'),
            ]);

            return $record->fresh()->load(['schedule', 'livestock.species', 'transaction']);
        }, 3);

        $message = $result->response === 'sold_already_recorded'
            ? 'Thank you. The sale was already recorded, so no duplicate transaction was created.'
            : (in_array($result->response, ['sold', 'sold_at_auction', 'sold_privately'], true) ? 'Your sale details were submitted for LGU Authority verification.' : 'Thank you. Your livestock status has been updated.');

        return response()->json(['message' => $message, 'data' => $result]);
    }

    public function lguIndex(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles', 'lguOfficerProfile']);
        abort_unless($user->roles->contains(fn ($role) => in_array($role->slug, ['lgu_encoder', 'lgu_authority'], true)), 403);
        $municipalityId = $user->lguOfficerProfile?->municipality_id;
        abort_unless($municipalityId, 403, 'Your account has no assigned municipality.');
        $query = AuctionFarmerFollowUp::query()->where('municipality_id', $municipalityId)
            ->with(['farmer.user:id,first_name,last_name,phone_number', 'schedule:id,title,auction_date,venue', 'livestock.species:id,name', 'transaction:id,status']);
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('response')) $query->where('response', $request->input('response'));
        $summary = AuctionFarmerFollowUp::query()->where('municipality_id', $municipalityId)->selectRaw("SUM(status = 'pending') as pending, SUM(status = 'overdue') as overdue, SUM(status = 'unresponsive') as unresponsive, SUM(status = 'responded') as responded")->first();
        return response()->json(['summary' => $summary, 'data' => $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'overdue' THEN 1 WHEN 'unresponsive' THEN 2 ELSE 3 END")->orderByDesc('auction_completed_at')->paginate(25)]);
    }

    public function lguShow(Request $request, string $followUp): JsonResponse
    {
        $user = $request->user()->loadMissing('lguOfficerProfile');
        $record = AuctionFarmerFollowUp::query()->whereKey($followUp)
            ->where('municipality_id', $user->lguOfficerProfile?->municipality_id)
            ->with(['farmer.user', 'schedule', 'livestock.species', 'livestock.breed', 'transaction'])
            ->firstOrFail();
        return response()->json(['data' => $record]);
    }
}
