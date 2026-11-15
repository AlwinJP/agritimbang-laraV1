import api from '../api';

const $ = (id) => document.getElementById(id);

const page = $('auction-followups-page');
const role = page?.dataset.role || 'farmer';
const endpoint =
    role === 'lgu'
        ? '/lgu/auction-follow-ups'
        : '/farmer/auction-follow-ups';

const state = {
    rows: [],
    page: 1,
    lastPage: 1,
    selected: null,
    barangays: [],
    pendingDeepLink: new URLSearchParams(window.location.search).get(
        'follow_up'
    ),
};

const esc = (v) =>
    String(v ?? '').replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c]
    );

const date = (v) =>
    v
        ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(
              new Date(v)
          )
        : '—';

function alert(message, error = false) {
    const el = $('followup-alert');
    el.textContent = message;
    el.className = `followup-alert${error ? ' is-error' : ''}`;
    el.hidden = false;
}

function statusLabel(s) {
    return (
        {
            pending: 'Awaiting response',
            overdue: 'Overdue',
            unresponsive: 'Unresponsive',
            responded: 'Responded',
        }[s] || s
    );
}

function responseLabel(s) {
    return (
        {
            sold: 'Sold (legacy record)',
            sold_at_auction: 'Sold at auction',
            sold_privately: 'Sold privately',
            sold_already_recorded: 'Sale already recorded',
            unsold: 'Still available',
            withdrawn: 'Withdrawn',
            not_brought: 'Not brought to auction',
        }[s] || 'No response yet'
    );
}

function render(payload) {
    const summary = payload.summary || {};

    for (const k of ['pending', 'overdue', 'unresponsive', 'responded']) {
        $(`fu-${k}`).textContent = Number(summary[k] || 0).toLocaleString(
            'en-PH'
        );
    }

    const rows = payload.data?.data || [];

    state.rows = rows;
    state.page = Number(payload.data?.current_page || 1);
    state.lastPage = Number(payload.data?.last_page || 1);

    $('followup-list').innerHTML = rows.length
        ? rows
              .map((r) => {
                  const animal = `${r.livestock?.species?.name || 'Livestock'}${
                      r.livestock?.breed?.name
                          ? ' · ' + r.livestock.breed.name
                          : ''
                  }`;

                  const farmer = r.farmer?.user
                      ? `${r.farmer.user.first_name || ''} ${
                            r.farmer.user.last_name || ''
                        }`.trim()
                      : '';

                  const meta = role === 'lgu' ? `${esc(farmer)} · ` : '';

                  return `
                    <article class="followup-card">
                        <div>
                            <h3>${esc(animal)}</h3>
                            <p>${meta}${esc(
                                r.schedule?.title || 'Auction'
                            )} · ${esc(date(r.schedule?.auction_date))}</p>
                            <p>${esc(
                                r.schedule?.venue || ''
                            )} · Auction completed ${esc(
                                date(r.auction_completed_at)
                            )}</p>
                            <p>${esc(responseLabel(r.response))}${
                                r.transaction
                                    ? ` · Transaction: ${esc(
                                          r.transaction.status
                                      )}`
                                    : ''
                            }</p>
                        </div>
                        <div class="followup-card-actions">
                            <span class="followup-status ${esc(r.status)}">
                                ${esc(statusLabel(r.status))}
                            </span>
                            ${
                                role === 'farmer' && !r.responded_at
                                    ? `<button class="followup-button" data-respond="${esc(
                                          r.id
                                      )}">Update status</button>`
                                    : ''
                            }
                        </div>
                    </article>
                `;
              })
              .join('')
        : '<p class="followup-muted">No auction follow-ups found. You are all caught up.</p>';

    $('fu-page-info').textContent = `Page ${state.page} of ${state.lastPage}`;
    $('fu-prev').disabled = state.page <= 1;
    $('fu-next').disabled = state.page >= state.lastPage;

    if (state.pendingDeepLink) {
        const target = rows.find(
            (r) => String(r.id) === String(state.pendingDeepLink)
        );

        if (target && role === 'farmer' && !target.responded_at) {
            state.pendingDeepLink = null;
            openDialog(target);
        } else if (target) {
            state.pendingDeepLink = null;
            alert(
                'This follow-up has already been answered. You can review its recorded response.'
            );
        }
    }
}

async function load(p = 1) {
    const params = { page: p };

    if (role === 'farmer' && state.pendingDeepLink) {
        params.follow_up = state.pendingDeepLink;
    }

    if ($('fu-filter-status').value) {
        params.status = $('fu-filter-status').value;
    }

    if (role === 'lgu' && $('fu-filter-response').value) {
        params.response = $('fu-filter-response').value;
    }

    const res = await api.get(endpoint, { params });

    render(res.data);
}

function openDialog(r) {
    state.selected = r;

    $('fu-record-id').value = r.id;

    $('fu-dialog-context').textContent = `${r.livestock?.species?.name || 'Livestock'} · ${
        r.schedule?.title || 'Auction'
    } · ${date(r.schedule?.auction_date)}`;

    $('fu-response').value = '';
    $('fu-sale-fields').hidden = true;

    $('fu-sale-date').value =
        r.schedule?.auction_date || new Date().toISOString().slice(0, 10);

    $('fu-sale-price').value = '';
    $('fu-sale-purpose').value = r.livestock?.sale_purpose || '';
    $('fu-selling-barangay').value = '';
    $('fu-private-location-field').hidden = true;
    $('fu-buyer-name').value = '';
    $('fu-buyer-phone').value = '';
    $('fu-notes').value = '';

    $('followup-response-dialog').showModal();
}

function closeDialog() {
    const d = $('followup-response-dialog');

    if (d.open) {
        d.close();
    }
}

$('followup-list').addEventListener('click', (e) => {
    const b = e.target.closest('[data-respond]');

    if (b) {
        const r = state.rows.find((x) => x.id === b.dataset.respond);

        if (r) {
            openDialog(r);
        }
    }
});

$('fu-response').addEventListener('change', () => {
    const value = $('fu-response').value;
    const isSale = ['sold', 'sold_at_auction', 'sold_privately'].includes(
        value
    );

    $('fu-sale-fields').hidden = !isSale;
    $('fu-private-location-field').hidden = value !== 'sold_privately';
});

$('followup-response-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const response = $('fu-response').value;

    if (!response) {
        return alert('Choose what happened to your livestock.', true);
    }

    const body = {
        response,
        notes: $('fu-notes').value || null,
    };

    if (['sold', 'sold_at_auction', 'sold_privately'].includes(response)) {
        body.transaction_date = $('fu-sale-date').value;
        body.actual_selling_price = $('fu-sale-price').value;
        body.sale_purpose = $('fu-sale-purpose').value;
        body.buyer_name = $('fu-buyer-name').value || null;
        body.buyer_phone = $('fu-buyer-phone').value || null;

        if (
            !body.transaction_date ||
            !body.actual_selling_price ||
            !body.sale_purpose
        ) {
            return alert(
                'Complete the sale date, selling price, and sale purpose.',
                true
            );
        }

        if (response === 'sold_privately') {
            body.selling_barangay_id = $('fu-selling-barangay').value;

            if (!body.selling_barangay_id) {
                return alert(
                    'Choose the barangay where the private sale happened.',
                    true
                );
            }
        }
    }

    const button = e.submitter;
    button.disabled = true;

    try {
        const res = await api.post(
            `/farmer/auction-follow-ups/${encodeURIComponent(
                $('fu-record-id').value
            )}/respond`,
            body
        );

        closeDialog();

        alert(res.data?.message || 'Your response has been saved.');

        await load(state.page);
    } catch (err) {
        alert(
            err.response?.data?.message ||
                Object.values(err.response?.data?.errors || {})
                    .flat()
                    .join(' ') ||
                err.message,
            true
        );
    } finally {
        button.disabled = false;
    }
});

$('fu-dialog-close').addEventListener('click', closeDialog);
$('fu-dialog-cancel').addEventListener('click', closeDialog);

$('followup-refresh').addEventListener('click', () =>
    load(state.page).catch((e) => alert(e.message, true))
);

$('fu-filter-status').addEventListener('change', () =>
    load(1).catch((e) => alert(e.message, true))
);

$('fu-filter-response').addEventListener('change', () =>
    load(1).catch((e) => alert(e.message, true))
);

$('fu-prev').addEventListener('click', () =>
    load(Math.max(1, state.page - 1))
);

$('fu-next').addEventListener('click', () =>
    load(Math.min(state.lastPage, state.page + 1))
);

if (role === 'lgu') {
    $('followup-title').textContent = 'Farmer Auction Follow-ups';
    $('followup-subtitle').textContent =
        'Monitor farmer responses after completed auctions.';
    $('followup-list-title').textContent = 'Municipal follow-up monitoring';
    $('followup-list-caption').textContent =
        'Use overdue and unresponsive records to coordinate MAO assistance.';
    $('fu-filter-response').hidden = false;
} else {
    $('fu-filter-response').hidden = true;
}

async function loadFarmerBarangays() {
    if (role !== 'farmer') {
        return;
    }

    try {
        const profileRes = await api.get('/farmer/profile');
        const profile = profileRes.data?.data ?? profileRes.data;

        const municipalityId = profile?.municipality_id;
        const homeBarangayId = profile?.barangay_id;

        if (!municipalityId) {
            return;
        }

        const res = await api.get('/gis/barangays', {
            params: { municipality_id: municipalityId },
        });

        const payload = res.data?.data ?? res.data;

        state.barangays = Array.isArray(payload)
            ? payload
            : payload?.data || [];

        const select = $('fu-selling-barangay');

        state.barangays.forEach((b) =>
            select.add(new Option(b.name, String(b.id)))
        );

        if (homeBarangayId) {
            select.value = String(homeBarangayId);
        }
    } catch (e) {
        console.warn(
            'Unable to load barangays for private-sale follow-up.',
            e
        );
    }
}

Promise.all([loadFarmerBarangays(), load()]).catch((e) =>
    alert(e.response?.data?.message || e.message, true)
);