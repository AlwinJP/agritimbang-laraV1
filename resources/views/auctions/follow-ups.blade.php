@extends('layouts.app')
@section('title', 'Auction Follow-ups')
@push('styles')
    @vite(['resources/css/auction-follow-ups.css', 'resources/js/pages/auction-follow-ups.js'])
@endpush
@section('content')
<div class="followup-page" id="auction-followups-page" data-role="{{ request()->is('lgu/*') ? 'lgu' : 'farmer' }}">
    <header class="followup-header"><div><span class="followup-eyebrow">AGRITIMBANG / AUCTION FOLLOW-UP</span><h1 id="followup-title">My Auction Follow-ups</h1><p id="followup-subtitle">Please update the status of livestock after an auction.</p></div><button class="followup-button is-light" id="followup-refresh" type="button">Refresh</button></header>
    <div class="followup-alert" id="followup-alert" role="status" hidden></div>
    <section class="followup-stats" id="followup-stats">
        <article><strong id="fu-pending">—</strong><span>Awaiting response</span></article>
        <article><strong id="fu-overdue">—</strong><span>Overdue</span></article>
        <article><strong id="fu-unresponsive">—</strong><span>Unresponsive</span></article>
        <article><strong id="fu-responded">—</strong><span>Responded</span></article>
    </section>
    <section class="followup-panel">
        <div class="followup-toolbar"><div><h2 id="followup-list-title">Follow-up records</h2><p id="followup-list-caption">Follow-ups stop sending automatic reminders after the final reminder.</p></div><div class="followup-filters"><select id="fu-filter-status"><option value="">All statuses</option><option value="pending">Awaiting response</option><option value="overdue">Overdue</option><option value="unresponsive">Unresponsive</option><option value="responded">Responded</option></select><select id="fu-filter-response"><option value="">All responses</option><option value="sold_at_auction">Sold at auction</option><option value="sold_privately">Sold privately</option><option value="sold_already_recorded">Sale already recorded</option><option value="unsold">Still available</option><option value="not_brought">Not brought to auction</option></select></div></div>
        <div class="followup-list" id="followup-list"><p class="followup-muted">Loading follow-ups…</p></div>
        <div class="followup-pagination"><span id="fu-page-info"></span><div><button type="button" class="followup-button is-light" id="fu-prev">Previous</button><button type="button" class="followup-button is-light" id="fu-next">Next</button></div></div>
    </section>
</div>
<dialog class="followup-dialog" id="followup-response-dialog">
    <form id="followup-response-form" method="dialog">
        <div class="followup-dialog-head"><div><span class="followup-eyebrow">POST-AUCTION UPDATE</span><h2>What happened to your livestock?</h2></div><button type="button" id="fu-dialog-close" aria-label="Close">×</button></div>
        <p class="followup-muted" id="fu-dialog-context">Please tell us what happened to this livestock after the auction. If the sale was already recorded by the Encoder, you do not need to submit it again.</p>
        <input type="hidden" id="fu-record-id">
        <label class="followup-field">Your response<select id="fu-response" required><option value="">Choose an answer</option><option value="sold_at_auction">It was sold during the auction</option><option value="sold_privately">It was sold privately, outside the auction</option><option value="unsold">It was not sold and is still available</option><option value="not_brought">It was not brought to the auction</option></select></label>
        <div id="fu-sale-fields" hidden>
            <label class="followup-field">Sale date<input type="date" id="fu-sale-date"></label>
            <label class="followup-field">Actual selling price (₱)<input type="number" id="fu-sale-price" min="0.01" step="0.01" placeholder="Enter actual amount"></label>
            <label class="followup-field">Sale purpose<select id="fu-sale-purpose"><option value="">Choose purpose</option><option value="slaughter">Slaughter</option><option value="breeding">Breeding</option></select></label>
            <label class="followup-field" id="fu-private-location-field" hidden>Where did the private sale happen?<select id="fu-selling-barangay"><option value="">Choose selling barangay</option></select></label>
            <div class="followup-two"><label class="followup-field">Buyer name (optional)<input id="fu-buyer-name" maxlength="255"></label><label class="followup-field">Buyer contact (optional)<input id="fu-buyer-phone" maxlength="13"></label></div>
        </div>
        <label class="followup-field">Additional notes (optional)<textarea id="fu-notes" rows="2" maxlength="1000"></textarea></label>
        <div class="followup-dialog-actions"><button type="button" class="followup-button is-light" id="fu-dialog-cancel">Cancel</button><button type="submit" class="followup-button">Submit response</button></div>
    </form>
</dialog>
