@extends('layouts.app')

@section('title', 'Assist Auction Transaction')

@vite([
    'resources/css/transaction-assist.css',
    'resources/js/pages/transaction-assist.js'
])

@section('content')
    <div class="tx-page" id="transaction-assist-page">
        <header class="tx-header">
            <div>
                <span class="tx-eyebrow">LGU ENCODER WORKSPACE</span>
                <h1>Assist Auction Transaction</h1>
                <p>Record livestock sold at scheduled auctions on behalf of verified farmers.</p>
            </div>
            <a href="/dashboard" class="tx-back">Back to Dashboard</a>
        </header>

        <div id="tx-notice" class="tx-notice" hidden></div>

        <div class="tx-layout">
            <main class="tx-main">
                <section class="tx-card tx-mode-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">01</span>
                        <div>
                            <h2>Select Auction Event</h2>
                            <p>Choose the auction where the livestock was sold.</p>
                        </div>
                    </div>
                    <div id="tx-auction-event-wrap" class="tx-auction-event-wrap">
                        <label for="tx-auction-event">Auction Event *</label>
                        <select id="tx-auction-event" required><option value="">Loading auction events…</option></select>
                        <p class="tx-muted">Only ongoing and completed auction events are available for recording actual sales.</p>
                    </div>
                </section>

                <section class="tx-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">02</span>
                        <div>
                            <h2>Select Farmer</h2>
                            <p>Search by farmer name, email, or registration code.</p>
                        </div>
                    </div>

                    <label for="tx-farmer-search">Find a farmer</label>
                    <input id="tx-farmer-search" type="search" placeholder="Enter a farmer name, email, or registration code..." autocomplete="off">

                    <div id="tx-farmer-results" class="tx-results"></div>
                    <div id="tx-selected-farmer" class="tx-selected" hidden></div>
                </section>

                <section class="tx-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">03</span>
                        <div>
                            <h2>Select Livestock</h2>
                            <p>Choose livestock registered for this auction, or add livestock sold at this event.</p>
                        </div>
                    </div>

                    <div id="tx-livestock-list" class="tx-livestock-list">
                        <p class="tx-muted">Select a farmer to view livestock.</p>
                    </div>
                    <div class="tx-manual-add-row">
                        <button type="button" id="tx-add-livestock" class="tx-secondary-button" disabled>+ Add New Livestock</button>
                        <small>Add an animal that is not yet in this farmer's livestock records.</small>
                    </div>
                </section>

                <section class="tx-card">
                    <div class="tx-card-heading">
                        <span class="tx-step">04</span>
                        <div>
                            <h2>Auction Sale Details</h2>
                            <p>Enter the sale details for the selected farmer. Buyer information is optional.</p>
                        </div>
                    </div>

                    <form id="tx-form">
                        <div class="tx-fields">
                            <label>
                                Buyer Name <span class="tx-muted">Optional</span>
                                <input id="tx-buyer-name" maxlength="255" placeholder="Enter buyer's name if available">
                            </label>

                            <label>
                                Buyer Mobile Number <span class="tx-muted">Optional</span>
                                <input id="tx-buyer-phone" type="tel" maxlength="13" placeholder="09XXXXXXXXX">
                            </label>

                            <label>
                                Transaction Date *
                                <input id="tx-date" type="date" required>
                            </label>

                            <label>
                                Sale Happened in Barangay *
                                <select id="tx-selling-barangay" required><option value="">Select farmer first</option></select>
                            </label>

                            <label class="tx-full">
                                Remarks
                                <textarea id="tx-remarks" rows="3" maxlength="2000"></textarea>
                            </label>
                        </div>

                        <h3>Selected Livestock, Sale Purpose & Selling Prices</h3>
                        <div id="tx-selected-items">
                            <p class="tx-muted">No livestock selected.</p>
                        </div>

                        <div class="tx-actions">
                            <button type="submit" id="tx-save">
                                Save Transaction Draft
                            </button>
                        </div>
                    </form>
                </section>
            </main>

            <aside class="tx-summary">
                <div class="tx-card">
                    <span class="tx-eyebrow">TRANSACTION SUMMARY</span>
                    <h2>Sale Overview</h2>

                    <div class="tx-summary-row">
                        <span>Selected livestock</span>
                        <strong id="tx-count">0</strong>
                    </div>

                    <div class="tx-summary-row">
                        <span>Total reference value</span>
                        <strong id="tx-reference">₱0.00</strong>
                    </div>

                    <div class="tx-summary-row tx-total">
                        <span>Total selling price</span>
                        <strong id="tx-total">₱0.00</strong>
                    </div>

                    <p class="tx-muted">
                        Reference values are based on saved livestock valuations.
                        Actual selling prices may differ.
                    </p>

                    <div id="tx-draft-actions" hidden>
                        <p id="tx-draft-message"></p>
                        <button type="button" id="tx-submit">
                            Submit for LGU Verification
                            </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <dialog id="tx-manual-livestock-dialog" class="tx-manual-dialog">
        <form id="tx-manual-livestock-form" method="dialog">
            <header><h2>Add New Livestock</h2><button type="button" id="tx-manual-close" aria-label="Close">×</button></header>
            <p class="tx-muted">Create a livestock record under the selected farmer and link it to the selected auction event. The entered actual weight is used to prepare its reference valuation.</p>
            <div class="tx-fields">
                <label>Species *<select id="tx-manual-species" required><option value="">Choose species</option></select></label>
                <label>Breed <select id="tx-manual-breed"><option value="">Unspecified breed</option></select></label>
                <label>Sex *<select id="tx-manual-sex" required><option value="">Choose sex</option><option value="male">Male</option><option value="female">Female</option><option value="unknown">Unknown</option></select></label>
                <label>Age (months)<input id="tx-manual-age" type="number" min="0" max="600" step="1" placeholder="Optional"></label>
                <label>Actual Weight (kg) *<input id="tx-manual-weight" type="number" min="0.01" step="0.01" required placeholder="Enter measured weight"></label>
                <label>Sale Purpose *<select id="tx-manual-purpose" required><option value="slaughter">Slaughter</option><option value="breeding">Breeding</option></select></label>
            </div>
            <div id="tx-manual-error" class="tx-notice tx-error" hidden></div>
            <footer><button type="button" id="tx-manual-cancel" class="tx-secondary-button">Cancel</button><button type="submit" id="tx-manual-save">Create Livestock</button></footer>
        </form>
    </dialog>
@endsection