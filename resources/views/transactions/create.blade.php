@extends('layouts.app')

@section('title', 'Record a Sale')

@push('styles')
    @vite('resources/css/transaction-create.css')
@endpush

@section('content')
<div class="sale-page" id="sale-page">
    <div class="sale-heading">
        <div>
            <p class="sale-eyebrow">FARMER TRANSACTIONS</p>
            <h1>Record a Sale</h1>
            <p>Tell us what you sold. We'll take care of the recording process.</p>
        </div>
        <a href="{{ route('transactions.index') }}" class="sale-back">← My Transactions</a>
    </div>

        <div id="sale-alert" class="sale-alert" hidden role="status"></div>

    <div class="sale-progress" aria-label="Transaction steps">
        <div class="sale-progress-step active" data-progress="1"><span>1</span><small>Livestock</small></div>
        <i></i><div class="sale-progress-step" data-progress="2"><span>2</span><small>Sale details</small></div>
        <i></i><div class="sale-progress-step" data-progress="3"><span>3</span><small>Buyer</small></div>
        <i></i><div class="sale-progress-step" data-progress="4"><span>4</span><small>Review</small></div>
    </div>

    <form id="sale-form" novalidate>
        <section class="sale-step active" data-step="1">
            <div class="sale-card">
                <div class="sale-step-title"><span>STEP 1 OF 4</span><h2>What did you sell?</h2><p>Select the livestock you sold. You can add more than one animal.</p></div>
                <div id="sale-auction-warning" class="sale-auction-warning" hidden role="status"></div>
                <div id="sale-items"></div>
                <button type="button" id="add-item" class="sale-btn sale-btn-outline">＋ Add another livestock</button>
                <div class="sale-total"><span>Total selling price</span><strong id="sale-total">₱0.00</strong></div>
            </div>
        </section>

        <section class="sale-step" data-step="2" hidden>
            <div class="sale-card">
                <div class="sale-step-title"><span>STEP 2 OF 4</span><h2>Tell us about the sale</h2><p>Enter when and where the sale happened.</p></div>
                <div class="sale-grid">
                    <label>When did you sell the livestock?<input name="transaction_date" type="date" required></label>
                    <label class="sale-span-2">Which barangay did the sale happen in?<select name="selling_barangay_id" required><option value="">Choose barangay</option></select></label>
                </div>
            </div>
        </section>

        <section class="sale-step" data-step="3" hidden>
            <div class="sale-card">
                <div class="sale-step-title"><span>STEP 3 OF 4</span><h2>Buyer information</h2><p>This part is optional. You can continue even if you don't know or don't want to provide the buyer's details.</p></div>
                <div class="sale-grid">
                    <label>Buyer's name <em>Optional</em><input name="buyer_name" maxlength="255" placeholder="Enter buyer's name"></label>
                    <label>Buyer's mobile number <em>Optional</em><input name="buyer_phone" type="tel" maxlength="13" pattern="(?:09[0-9]{9}|\+639[0-9]{9})" title="Use 09XXXXXXXXX or +639XXXXXXXXX" placeholder="09XXXXXXXXX"><small>Only provide this if the buyer agrees to share it.</small></label>
                    <label class="sale-span-2">Additional notes <em>Optional</em><textarea name="remarks" rows="3" maxlength="2000" placeholder="Anything else you want the MAO to know?"></textarea></label>
                </div>
            </div>
        </section>

        <section class="sale-step" data-step="4" hidden>
            <div class="sale-card">
                <div class="sale-step-title"><span>STEP 4 OF 4</span><h2>Review your sale</h2><p>Please check the details before submitting.</p></div>
                <div id="sale-review" class="sale-review"></div>
                <div class="sale-review-note"><strong>What happens next?</strong><p>Your sale will be sent to the Municipal Agriculture Office for verification. You can check its status under My Transactions.</p></div>
            </div>
        </section>

        <div class="sale-actions">
            <button type="button" id="sale-back-step" class="sale-btn sale-btn-outline" hidden>Back</button>
            <button type="button" id="save-draft" class="sale-btn sale-btn-quiet">Save for later</button>
            <span class="sale-action-spacer"></span>
            <button type="button" id="sale-next-step" class="sale-btn sale-btn-primary">Continue</button>
            <button type="submit" id="submit-sale" class="sale-btn sale-btn-primary" hidden>Submit sale</button>
        </div>
    </form>
</div>
@endsection

@vite('resources/js/pages/transaction-create.js')
