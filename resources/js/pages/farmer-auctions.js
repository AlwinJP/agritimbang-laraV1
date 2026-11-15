import api from "../api";

const $ = (id) => document.getElementById(id);

const state = {
    schedules: [],
    registrations: [],
    registrationPage: 1,
    registrationLastPage: 1,
    statusTab: "open",
    filters: {
        species_id: "",
        auction_schedule_id: ""
    }
};

const esc = (v) =>
    String(v ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[c]);

const unwrap = (r) => r.data?.data ?? r.data;

function dateLabel(v) {
    if (!v) return "Date unavailable";

    const d = new Date(`${String(v).slice(0, 10)}T00:00:00+08:00`);

    return Number.isNaN(d.getTime())
        ? "Date unavailable"
        : d.toLocaleDateString("en-PH", {
            timeZone: "Asia/Manila",
            month: "short",
            day: "numeric",
            year: "numeric"
        });
}

function dateTimeLabel(v) {
    return v
        ? new Date(v).toLocaleString("en-PH", {
            timeZone: "Asia/Manila",
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit"
        })
        : "—";
}

function timeLabel(v) {
    if (!v) return "—";

    const [h, m] = String(v).slice(0, 5).split(":").map(Number);

    return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
}

function statusBadge(status) {
    return `<span class="auction-status-badge is-${esc(status)}">${esc(String(status ?? "").replaceAll("_", " "))}</span>`;
}

function showAlert(message, error = false) {
    const el = $("fa-alert");

    el.textContent = message;
    el.className = `auction-alert${error ? " is-error" : ""}`;
    el.hidden = false;
}

function scheduleCard(s, type) {
    const badge =
        type === "open"
            ? "Registration Open"
            : type === "ongoing"
                ? "Auction Ongoing"
                : "Registration Opens Soon";

    const action =
        type === "open"
            ? `<a class="auction-primary-button" href="/auctions/${encodeURIComponent(s.id)}">Register Livestock</a>`
            : `<a class="auction-secondary-button" href="/auctions/${encodeURIComponent(s.id)}">View Auction</a>`;

    return `
        <article class="auction-schedule-card">
            <div class="auction-card-top">
                <span class="auction-eyebrow">${esc(s.municipality?.name ?? "Municipal Auction")}</span>
                ${statusBadge(type)}
            </div>
            <h3>${esc(s.title)}</h3>
            <p class="auction-card-date">
                ${dateLabel(s.auction_date)} · ${timeLabel(s.start_time)}${s.expected_end_time ? ` – ${timeLabel(s.expected_end_time)}` : ""}
            </p>
            <p>${esc(s.venue)} · ${esc(s.barangay?.name ?? "")}</p>
            <div class="auction-card-meta">
                <span>${Number(s.registrations_count ?? 0)} livestock registered</span>
                <span class="auction-window-label">${esc(badge)}</span>
            </div>
            ${action}
        </article>
    `;
}

function switchStatusTab(tab) {
    state.statusTab = tab;

    document.querySelectorAll("[data-fa-status]").forEach((b) => {
        const active = b.dataset.faStatus === tab;

        b.classList.toggle("is-active", active);
        b.setAttribute("aria-selected", String(active));
    });

    document.querySelectorAll("[data-fa-slider]").forEach((el) => {
        el.hidden = el.dataset.faSlider !== tab;
    });
}

function renderSchedules() {
    const groups = {
        open: state.schedules.filter((s) => s.registration_open && s.status === "published"),
        ongoing: state.schedules.filter((s) => s.status === "ongoing"),
        soon: state.schedules.filter((s) => s.registration_has_not_opened && s.status === "published")
    };

    $("fa-open-count").textContent = groups.open.length;
    $("fa-ongoing-count").textContent = groups.ongoing.length;
    $("fa-soon-count").textContent = groups.soon.length;

    for (const type of ["open", "ongoing", "soon"]) {
        const list = groups[type];

        $(`fa-${type}-list`).innerHTML = list.length
            ? list.map((s) => scheduleCard(s, type)).join("")
            : `<p class="auction-muted auction-empty-note">${type === "open" ? "No auctions are accepting registrations right now." : type === "ongoing" ? "There are no ongoing auctions right now." : "No upcoming registration periods at the moment."}</p>`;
    }
}

async function loadSchedules() {
    const response = await api.get("/farmer/auction-schedules");
    const data = unwrap(response);

    state.schedules = Array.isArray(data) ? data : [];

    renderSchedules();
}

function switchTab(tab) {
    document.querySelectorAll("[data-fa-tab]").forEach((b) => {
        const active = b.dataset.faTab === tab;

        b.classList.toggle("is-active", active);
        b.setAttribute("aria-selected", String(active));
    });

    $("fa-available-panel").hidden = tab !== "available";
    $("fa-registrations-panel").hidden = tab !== "registrations";

    if (tab === "registrations") {
        loadRegistrations(1).catch((e) =>
            showAlert(e.response?.data?.message ?? e.message, true)
        );
    }
}

async function loadRegistrationFilters() {
    const response = await api.get("/farmer/auction-registration-filters");
    const data = unwrap(response) || {};

    const species = data.species || [];
    const schedules = data.schedules || [];

    const speciesSelect = $("fa-filter-species");
    const eventSelect = $("fa-filter-auction");

    speciesSelect.replaceChildren(new Option("All species", ""));

    species.forEach((s) => speciesSelect.add(new Option(s.name, s.id)));

    eventSelect.replaceChildren(new Option("All auction events", ""));

    schedules.forEach((s) =>
        eventSelect.add(new Option(`${s.title} · ${dateLabel(s.auction_date)}`, s.id))
    );
}

async function loadRegistrations(page = 1) {
    const response = await api.get("/farmer/auction-registrations", {
        params: {
            page,
            ...state.filters
        }
    });

    const payload = response.data;
    const records = payload?.data ?? [];

    state.registrations = records;
    state.registrationPage = Number(payload?.current_page ?? 1);
    state.registrationLastPage = Number(payload?.last_page ?? 1);

    $("fa-registration-body").innerHTML = records.length
        ? records.map((r) => `
            <tr>
                <td>
                    <strong>${esc(r.livestock?.species?.name ?? "Livestock")}</strong>
                    <small>${esc(r.livestock?.breed?.name ?? "Unspecified Breed")}</small>
                </td>
                <td>
                    <strong>${esc(r.schedule?.title ?? "Auction")}</strong>
                    <small>${dateLabel(r.schedule?.auction_date)} · ${timeLabel(r.schedule?.start_time)}</small>
                </td>
                <td>${esc(r.schedule?.venue ?? "—")}</td>
                <td>${dateTimeLabel(r.registered_at)}</td>
                <td>${statusBadge(r.status)}</td>
                <td>
                    <div class="fa-registration-actions">
                        <button type="button" class="auction-secondary-button" data-fa-view="${esc(r.id)}">
                            View
                        </button>
                        ${
                            r.status === "registered" &&
                            r.schedule?.status === "published" &&
                            r.schedule?.registration_closes_at &&
                            new Date() < new Date(r.schedule.registration_closes_at)
                                ? `<button type="button" class="auction-link-button is-danger" data-fa-withdraw="${esc(r.id)}">Withdraw</button>`
                                : ""
                        }
                    </div>
                </td>
            </tr>
        `).join("")
        : `<tr><td colspan="6" class="auction-empty-cell">No registrations match your filters.</td></tr>`;

    $("fa-registration-page-info").textContent = `Page ${state.registrationPage} of ${state.registrationLastPage}`;

    $("fa-registration-prev").disabled = state.registrationPage <= 1;
    $("fa-registration-next").disabled = state.registrationPage >= state.registrationLastPage;
}

function showRegistrationDetails(registration) {
    const animal = registration.livestock || {};
    const schedule = registration.schedule || {};

    const rows = [
        ["Livestock", animal.species?.name],
        ["Breed", animal.breed?.name || "Unspecified breed"],
        ["Sex", animal.sex],
        ["Age", animal.age_months != null ? `${animal.age_months} months` : animal.age_group],
        ["Weight", animal.actual_weight_kg != null ? `${animal.actual_weight_kg} kg` : "Not recorded"],
        ["Sale purpose", animal.sale_purpose],
        ["Auction", schedule.title],
        ["Auction date", dateLabel(schedule.auction_date)],
        ["Time", `${timeLabel(schedule.start_time)}${schedule.expected_end_time ? ` – ${timeLabel(schedule.expected_end_time)}` : ""}`],
        ["Venue", schedule.venue],
        ["Barangay", schedule.barangay?.name],
        ["Registration date", dateTimeLabel(registration.registered_at)],
        ["Registration status", String(registration.status || "").replaceAll("_", " ")]
    ];

    $("fa-registration-detail-content").innerHTML = `
        <dl class="fa-registration-detail-grid">
            ${rows.map(([label, value]) => `
                <div>
                    <dt>${esc(label)}</dt>
                    <dd>${esc(value ?? "—")}</dd>
                </div>
            `).join("")}
        </dl>
    `;

    $("fa-registration-dialog").showModal();
}

async function withdraw(id) {
    if (!confirm("Withdraw this livestock from the auction? You can register it again before the deadline if it remains eligible.")) {
        return;
    }

    try {
        const r = await api.post(`/farmer/auction-registrations/${encodeURIComponent(id)}/withdraw`);

        showAlert(r.data?.message || "Registration withdrawn.");

        await loadRegistrations();
        await loadRegistrationFilters();
        await loadSchedules();
    } catch (e) {
        showAlert(e.response?.data?.message ?? e.message ?? "Unable to withdraw registration.", true);
    }
}

function closeDetails() {
    const d = $("fa-registration-dialog");

    if (d?.open) d.close();
}

function bind() {
    document.querySelectorAll("[data-fa-tab]").forEach((b) =>
        b.addEventListener("click", () => switchTab(b.dataset.faTab))
    );

    document.querySelectorAll("[data-fa-status]").forEach((b) =>
        b.addEventListener("click", () => switchStatusTab(b.dataset.faStatus))
    );

    $("fa-refresh").addEventListener("click", () => {
        loadSchedules().catch((e) =>
            showAlert(e.response?.data?.message ?? e.message, true)
        );

        if (!$("fa-registrations-panel").hidden) {
            loadRegistrations().catch((e) => showAlert(e.message, true));
        }
    });

    $("fa-registration-body").addEventListener("click", (e) => {
        const view = e.target.closest("[data-fa-view]");
        const withdrawButton = e.target.closest("[data-fa-withdraw]");

        if (view) {
            const row = state.registrations.find(
                (r) => String(r.id) === String(view.dataset.faView)
            );

            if (row) showRegistrationDetails(row);
        }

        if (withdrawButton) {
            withdraw(withdrawButton.dataset.faWithdraw);
        }
    });

    $("fa-filter-species").addEventListener("change", () => {
        state.filters.species_id = $("fa-filter-species").value;

        loadRegistrations(1).catch((e) => showAlert(e.message, true));
    });

    $("fa-filter-auction").addEventListener("change", () => {
        state.filters.auction_schedule_id = $("fa-filter-auction").value;

        loadRegistrations(1).catch((e) => showAlert(e.message, true));
    });

    $("fa-clear-filters").addEventListener("click", () => {
        state.filters = {
            species_id: "",
            auction_schedule_id: ""
        };

        $("fa-filter-species").value = "";
        $("fa-filter-auction").value = "";

        loadRegistrations(1).catch((e) => showAlert(e.message, true));
    });

    $("fa-registration-prev").addEventListener("click", () =>
        loadRegistrations(Math.max(1, state.registrationPage - 1))
    );

    $("fa-registration-next").addEventListener("click", () =>
        loadRegistrations(Math.min(state.registrationLastPage, state.registrationPage + 1))
    );

    $("fa-registration-close").addEventListener("click", closeDetails);
    $("fa-registration-close-footer").addEventListener("click", closeDetails);
}

async function init() {
    bind();

    try {
        const me = await api.get("/auth/me");
        const user = me.data?.data;

        if (!(user?.roles ?? []).some((r) => (r.slug ?? r) === "farmer")) {
            throw new Error("Only Farmers can access auction schedules.");
        }

        await Promise.all([
            loadSchedules(),
            loadRegistrationFilters()
        ]);

        switchStatusTab("open");
    } catch (e) {
        showAlert(e.response?.data?.message ?? e.message ?? "Unable to load auction schedules.", true);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}