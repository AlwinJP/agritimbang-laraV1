@extends('layouts.app')

@section('title', 'Livestock Auctions')

@push('styles')
    @vite(['resources/css/auction-management.css', 'resources/js/pages/farmer-auctions.js'])
@endpush

@section('content')
    <div class="auction-workspace" id="farmer-auctions-page">
        <header class="auction-workspace-header">
            <div>
                <span class="auction-eyebrow">FARMER WORKSPACE / AUCTIONS</span>
                <h1>Livestock Auctions</h1>
                <p>Find municipal auctions and register your eligible livestock.</p>
            </div>
            <button type="button" class="auction-secondary-button" id="fa-refresh">Refresh</button>
        </header>

        <div class="auction-alert" id="fa-alert" role="status" aria-live="polite" hidden></div>

        <div class="auction-tabs" role="tablist" aria-label="Auction pages">
            <button type="button" class="is-active" data-fa-tab="available" role="tab" aria-selected="true">Available Auctions</button>
            <button type="button" data-fa-tab="registrations" role="tab" aria-selected="false">My Auction Registrations</button>
        </div>

        <section id="fa-available-panel" class="auction-panel">
            <div class="fa-auction-status-tabs" role="tablist" aria-label="Auction status">
                <button type="button" class="is-active" data-fa-status="open" role="tab" aria-selected="true">Open <span id="fa-open-count">0</span></button>
                <button type="button" data-fa-status="ongoing" role="tab" aria-selected="false">Ongoing <span id="fa-ongoing-count">0</span></button>
                <button type="button" data-fa-status="soon" role="tab" aria-selected="false">Soon <span id="fa-soon-count">0</span></button>
            </div>
            <div class="fa-auction-slider-wrap">
                <div id="fa-open-list" class="auction-schedule-grid fa-auction-slider" data-fa-slider="open">
                    <p class="auction-muted">Loading open auctions...</p>
                </div>
                <div id="fa-ongoing-list" class="auction-schedule-grid fa-auction-slider" data-fa-slider="ongoing" hidden>
                    <p class="auction-muted">Loading ongoing auctions...</p>
                </div>
                <div id="fa-soon-list" class="auction-schedule-grid fa-auction-slider" data-fa-slider="soon" hidden>
                    <p class="auction-muted">Loading upcoming auctions...</p>
                </div>
            </div>
        </section>

        <section id="fa-registrations-panel" class="auction-panel" hidden>
            <div class="auction-panel-toolbar">
                <div>
                    <h2>My Auction Registrations</h2>
                    <p>Review the livestock you have registered and withdraw before the deadline if needed.</p>
                </div>
            </div>

            <div class="fa-registration-filters">
                <label>Species
                    <select id="fa-filter-species"><option value="">All species</option></select>
                </label>
                <label>Auction Event
                    <select id="fa-filter-auction"><option value="">All auction events</option></select>
                </label>
                <button type="button" class="auction-secondary-button" id="fa-clear-filters">Clear filters</button>
            </div>

            <div class="auction-table-scroll">
                <table class="auction-data-table">
                    <thead>
                        <tr>
                            <th>Livestock</th>
                            <th>Auction Schedule</th>
                            <th>Venue</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="fa-registration-body">
                        <tr>
                            <td colspan="6">Loading registrations...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="auction-pagination">
                <span id="fa-registration-page-info"></span>
                <div>
                    <button type="button" class="auction-secondary-button" id="fa-registration-prev">Previous</button>
                    <button type="button" class="auction-secondary-button" id="fa-registration-next">Next</button>
                </div>
            </div>
        </section>

        <dialog id="fa-registration-dialog" class="fa-registration-dialog">
            <div class="fa-registration-dialog-head"><h2>Registration Details</h2><button type="button" id="fa-registration-close" aria-label="Close">×</button></div>
            <div id="fa-registration-detail-content"></div>
            <div class="fa-registration-dialog-actions"><button type="button" class="auction-secondary-button" id="fa-registration-close-footer">Close</button></div>
        </dialog>
    </div>
@endsection