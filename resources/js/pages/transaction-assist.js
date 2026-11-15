import api from "../api";

const $ = (id) => document.getElementById(id);

const page = $("transaction-assist-page");

if (page) {
    const state = {
        farmer: null,
        livestock: [],
        selected: new Map(),
        draft: null,
        transactionType: "auction_sale",
        auctionSchedule: null,
        auctionSchedules: [],
        loading: false,
        searchRequest: 0,
        livestockRequest: 0,
    };

    const money = (value) =>
        new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
        }).format(Number(value) || 0);

    const unwrap = (response) => response.data?.data ?? {};

    const text = (value) => String(value ?? "");

    function el(tag, className, content) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (content !== undefined) node.textContent = text(content);
        return node;
    }

    function notice(message, error = false) {
        const target = $("tx-notice");
        target.hidden = false;
        target.textContent = message;
        target.className = `tx-notice ${error ? "tx-error" : "tx-success"}`;
    }

    function errorMessage(error) {
        const errors = error.response?.data?.errors;
        const first = errors && Object.values(errors).flat()[0];

        return (
            first ||
            error.response?.data?.message ||
            error.message ||
            "Something went wrong."
        );
    }

    function farmerName(farmer) {
        const user = farmer.user ?? {};

        return (
            [user.first_name, user.last_name].filter(Boolean).join(" ") ||
            farmer.farmer_code ||
            "Farmer"
        );
    }

    function latestValuation(livestock) {
        const valuations = livestock.valuations ?? [];

        return (
            valuations.find(
                (valuation) =>
                    Number(valuation.estimated_value) > 0 &&
                    Number(valuation.weight_used_kg) > 0,
            ) ?? null
        );
    }

    function setDraftLocked(locked) {
        $("tx-farmer-search").disabled = locked;
        $("tx-auction-event").disabled = locked;
        $("tx-add-livestock").disabled = locked || !state.farmer || !state.auctionSchedule;
        $("tx-form")
            .querySelectorAll("input, textarea, button")
            .forEach((node) => {
                node.disabled = locked;
            });

        document.querySelectorAll(".tx-livestock-check").forEach((node) => {
            node.disabled = locked;
        });
    }

    function renderFarmers(farmers) {
        const container = $("tx-farmer-results");
        container.replaceChildren();

        if (!farmers.length) {
            container.append(
                el("p", "tx-muted", "No verified farmers match your search. Try another name, email, or registration code."),
            );
            return;
        }

        farmers.forEach((farmer) => {
            const button = el("button", "tx-farmer-option");
            button.type = "button";

            button.append(
                el("strong", "", farmerName(farmer)),
                el("span", "", farmer.farmer_code || "No farmer code"),
            );

            button.addEventListener("click", () => selectFarmer(farmer));

            container.append(button);
        });
    }

    async function searchFarmers() {
        const requestId = ++state.searchRequest;
        const search = $("tx-farmer-search").value.trim();
        const container = $("tx-farmer-results");

        if (!state.auctionSchedule) {
            container.replaceChildren(el("p", "tx-muted", "Select an auction event before searching for a participating farmer."));
            return;
        }

        // Never request or display the full farmer directory. Results appear
        // only after the Encoder enters at least two search characters.
        if (search.length < 2) {
            container.replaceChildren();
            if (search.length === 1) {
                container.append(
                    el("p", "tx-muted", "Type at least 2 characters to search for a farmer."),
                );
            }
            return;
        }

        try {
            const response = await api.get(
                "/lgu/transactions/encoding/farmers",
                { params: { search, per_page: 20 } },
            );

            if (requestId !== state.searchRequest) return;

            const payload = unwrap(response);
            renderFarmers(payload.data ?? []);
        } catch (error) {
            if (requestId !== state.searchRequest) return;
            notice(errorMessage(error), true);
        }
    }

    async function selectFarmer(farmer) {
        state.farmer = farmer;
        state.selected.clear();
        state.livestock = [];

        $("tx-selected-farmer").hidden = false;
        $("tx-selected-farmer").textContent =
            `${farmerName(farmer)} — ${farmer.farmer_code || "Registered farmer"}`;
        $("tx-add-livestock").disabled = !state.auctionSchedule;

        $("tx-farmer-results").replaceChildren();
        $("tx-farmer-search").value = farmerName(farmer);

        renderSelected();
        updateSummary();
        await loadSellingBarangays(farmer);
        const auctionBarangayId = state.auctionSchedule?.barangay_id || state.auctionSchedule?.auction_house?.barangay_id;
        if (state.transactionType === "auction_sale" && auctionBarangayId) {
            $("tx-selling-barangay").value = String(auctionBarangayId);
        }

        const requestId = ++state.livestockRequest;
        const container = $("tx-livestock-list");

        container.replaceChildren(el("p", "tx-muted", "Loading livestock..."));

        try {
            const response = await api.get(
                `/lgu/transactions/encoding/farmers/${farmer.id}/livestock`,
                { params: { auction_schedule_id: state.auctionSchedule.id } },
            );

            if (requestId !== state.livestockRequest) return;

            state.livestock = unwrap(response);
            renderLivestock();
        } catch (error) {
            if (requestId !== state.livestockRequest) return;
            container.replaceChildren();
            notice(errorMessage(error), true);
        }
    }


    async function loadSellingBarangays(farmer) {
        const select = $("tx-selling-barangay");
        select.replaceChildren(new Option("Loading barangays...", ""));
        try {
            const response = await api.get("/gis/barangays", { params: { municipality_id: farmer.municipality_id } });
            const payload = unwrap(response);
            const barangays = Array.isArray(payload) ? payload : (payload.data || []);
            select.replaceChildren(new Option("Choose barangay", ""));
            barangays.forEach((barangay) => select.add(new Option(barangay.name, String(barangay.id))));
            if (farmer.barangay_id && barangays.some((b) => String(b.id) === String(farmer.barangay_id))) {
                select.value = String(farmer.barangay_id);
            }
        } catch (error) {
            select.replaceChildren(new Option("Unable to load barangays", ""));
            notice("Could not load barangays for the selected farmer. Please refresh and try again.", true);
        }
    }

    function renderLivestock() {
        const container = $("tx-livestock-list");
        container.replaceChildren();

        if (!state.livestock.length) {
            container.append(
                el("p", "tx-muted", "No available livestock found."),
            );
            return;
        }

        state.livestock.forEach((livestock) => {
            const valuation = latestValuation(livestock);

            const card = el("label", "tx-livestock-card");
            card.dataset.livestockId = String(livestock.id);
            const checkbox = document.createElement("input");

            checkbox.type = "checkbox";
            checkbox.className = "tx-livestock-check";
            checkbox.disabled = livestock.selectable === false;

            const details = el("div", "tx-livestock-details");

            details.append(
                el(
                    "strong",
                    "",
                    `${livestock.species?.name || "Livestock"} — ${
                        livestock.breed?.name || "Unspecified breed"
                    }`,
                ),
                el(
                    "span",
                    "",
                    valuation
                        ? `Reference: ${money(valuation.estimated_value)} · Weight: ${valuation.weight_used_kg} kg`
                        : "No saved valuation — Encoder may enter the actual selling price.",
                ),
            );
            if (["registered", "pending_sale_review", "completed"].includes(livestock.auction_participation_status)) {
                details.append(el("span", "tx-auction-flag", "Linked to the selected auction event."));
            }
            if (livestock.selectable === false) {
                details.append(el("span", "tx-livestock-blocked", livestock.selection_block_reason || "This livestock cannot be selected."));
            }

            checkbox.addEventListener("change", () => {
                if (checkbox.checked) {
                    state.selected.set(livestock.id, {
                        livestock,
                        valuation,
                        sellingPrice: valuation ? Number(valuation.estimated_value) : "",
                        salePurpose: "slaughter",
                    });
                } else {
                    state.selected.delete(livestock.id);
                }

                renderSelected();
                updateSummary();
            });

            card.append(checkbox, details);
            container.append(card);
        });
    }

    function renderSelected() {
        const container = $("tx-selected-items");
        container.replaceChildren();

        if (!state.selected.size) {
            container.append(el("p", "tx-muted", "No livestock selected."));
            return;
        }

        state.selected.forEach((item, id) => {
            const row = el("div", "tx-selected-row");
            const label = el(
                "span",
                "",
                `${item.livestock.species?.name || "Livestock"} · ${item.valuation ? `Reference ${money(item.valuation.estimated_value)}` : "No saved valuation"}`,
            );

            const input = document.createElement("input");
            input.type = "number";
            input.min = "0.01";
            input.step = "0.01";
            input.required = true;
            input.value = item.sellingPrice;

            input.addEventListener("input", () => {
                const selected = state.selected.get(id);
                if (!selected) return;

                selected.sellingPrice = input.value;
                updateSummary();
            });

            const purpose = document.createElement("select");
            purpose.className = "tx-sale-purpose";
            purpose.innerHTML = '<option value="slaughter">Slaughter</option><option value="breeding">Breeding</option>';
            purpose.value = item.salePurpose || "slaughter";
            purpose.addEventListener("change", () => {
                const selected = state.selected.get(id);
                if (selected) selected.salePurpose = purpose.value;
            });
            row.append(label, input, purpose);
            container.append(row);
        });
    }

    function updateSummary() {
        let reference = 0;
        let selling = 0;

        state.selected.forEach((item) => {
            reference += Number(item.valuation?.estimated_value) || 0;
            selling += Number(item.sellingPrice) || 0;
        });

        $("tx-count").textContent = state.selected.size;
        $("tx-reference").textContent = money(reference);
        $("tx-total").textContent = money(selling);
    }

    function collectPayload() {
        if (!state.auctionSchedule) throw new Error("Please select the auction event.");
        if (!state.farmer) throw new Error("Please select a farmer.");
        if (!state.selected.size) throw new Error("Please select at least one livestock item.");

        const sellingBarangayId = state.auctionSchedule?.barangay_id
            || state.auctionSchedule?.auction_house?.barangay_id
            || $("tx-selling-barangay")?.value
            || null;
        const sellingLocation = state.auctionSchedule?.venue
            || state.auctionSchedule?.auction_house?.name
            || state.auctionSchedule?.barangay?.name
            || $("tx-selling-barangay")?.selectedOptions?.[0]?.textContent
            || "Auction venue";

        const items = Array.from(state.selected.values()).map((item) => {
            const price = Number(item.sellingPrice);
            if (!Number.isFinite(price) || price <= 0 || !/^\d+(\.\d{1,2})?$/.test(String(item.sellingPrice))) {
                throw new Error("Enter a valid selling price for each livestock.");
            }
            return {
                livestock_id: item.livestock.id,
                ...(item.valuation?.id ? { valuation_id: item.valuation.id } : {}),
                sale_purpose: item.salePurpose || "slaughter",
                actual_selling_price: price,
            };
        });

        return {
            farmer_id: state.farmer.id,
            buyer_name: $("tx-buyer-name").value.trim() || null,
            buyer_phone: $("tx-buyer-phone").value.trim() || null,
            transaction_date: $("tx-date").value || String(state.auctionSchedule.auction_date).slice(0, 10),
            selling_location: sellingLocation,
            selling_barangay_id: sellingBarangayId,
            transaction_type: "auction",
            auction_schedule_id: state.auctionSchedule.id,
            remarks: $("tx-remarks").value.trim() || null,
            items,
        };
    }

    async function saveDraft(event) {
        event.preventDefault();

        if (state.loading || state.draft) return;

        try {
            const payload = collectPayload();

            state.loading = true;
            $("tx-save").disabled = true;

            const response = await api.post(
                "/lgu/transactions/encoding",
                payload,
            );

            state.draft = unwrap(response);

            setDraftLocked(true);
            $("tx-draft-actions").hidden = false;
            $("tx-draft-message").textContent =
                "Draft saved. Review the information before submitting.";

            notice("Transaction draft saved successfully.");
        } catch (error) {
            notice(errorMessage(error), true);
        } finally {
            state.loading = false;

            if (!state.draft) {
                $("tx-save").disabled = false;
            }
        }
    }

    async function submitDraft() {
        if (!state.draft || state.loading) return;

        const confirmed = window.confirm(
            "Submit this auction sale for LGU Authority verification? The selected livestock will be reserved while the transaction is reviewed."
        );
        if (!confirmed) return;

        state.loading = true;
        $("tx-submit").disabled = true;

        try {
            await api.post(
                `/lgu/transactions/encoding/${state.draft.id}/submit`,
            );

            $("tx-draft-message").textContent =
                "Submitted successfully. Awaiting LGU Authority verification.";

            $("tx-submit").hidden = true;

            notice("Transaction submitted for LGU verification.");
        } catch (error) {
            notice(errorMessage(error), true);
            $("tx-submit").disabled = false;
        } finally {
            state.loading = false;
        }
    }

    async function loadAuctionSchedules() {
        const select = $("tx-auction-event");
        select.replaceChildren(new Option("Loading auction events…", ""));
        try {
            const response = await api.get("/lgu/transactions/encoding/auction-schedules");
            const payload = response.data?.data ?? response.data ?? {};
            const allSchedules = Array.isArray(payload) ? payload : (payload.data ?? []);
            const schedules = allSchedules.filter((schedule) => ["ongoing", "completed"].includes(schedule.status) && schedule.auction_date);
            state.auctionSchedules = schedules;
            select.replaceChildren(new Option(schedules.length ? "Choose auction event" : "No dated auction events available", ""));
            schedules.forEach((schedule) => {
                const date = new Date(`${String(schedule.auction_date).slice(0, 10)}T00:00:00+08:00`);
                const dateLabel = !Number.isNaN(date.getTime()) ? date.toLocaleDateString("en-PH", { timeZone: "Asia/Manila", month: "short", day: "numeric", year: "numeric" }) : "Date unavailable";
                const venue = schedule.auction_house?.name || schedule.venue || "Auction venue";
                select.add(new Option(`${schedule.title || "Auction"} · ${dateLabel} · ${venue}`, schedule.id));
            });
            if (allSchedules.length > schedules.length) notice("Some auction schedules have no valid date and were excluded. Update their auction date in Auction Schedules.", true);
        } catch (error) {
            select.replaceChildren(new Option("Unable to load auction events", ""));
            notice(errorMessage(error), true);
        }
    }

    async function loadManualLivestockOptions() {
        const response = await api.get("/reference/species");
        const payload = unwrap(response);
        const species = Array.isArray(payload) ? payload : (payload.data ?? []);
        const select = $("tx-manual-species");
        select.replaceChildren(new Option("Choose species", ""));
        species.filter((item) => item.is_active !== false).forEach((item) => select.add(new Option(item.name, item.id)));
    }

    async function loadManualBreeds(speciesId) {
        const select = $("tx-manual-breed");
        select.replaceChildren(new Option("Unspecified breed", ""));
        if (!speciesId) return;
        const response = await api.get(`/reference/species/${encodeURIComponent(speciesId)}/breeds`);
        const payload = unwrap(response);
        const breeds = Array.isArray(payload) ? payload : (payload.data ?? []);
        breeds.filter((item) => item.is_active !== false).forEach((item) => select.add(new Option(item.name, item.id)));
    }

    function closeManualLivestock() { const dialog = $("tx-manual-livestock-dialog"); if (dialog?.open) dialog.close(); }

    async function saveManualLivestock(event) {
        event.preventDefault();
        if (!state.farmer || state.loading) return;
        const button = $("tx-manual-save"); const errorBox = $("tx-manual-error");
        errorBox.hidden = true; button.disabled = true;
        try {
            const payload = {
                species_id: $("tx-manual-species").value,
                breed_id: $("tx-manual-breed").value || null,
                sex: $("tx-manual-sex").value,
                age_months: $("tx-manual-age").value === "" ? null : Number($("tx-manual-age").value),
                actual_weight_kg: Number($("tx-manual-weight").value),
                sale_purpose: $("tx-manual-purpose").value,
                auction_schedule_id: state.auctionSchedule?.id,
            };
            const response = await api.post(`/lgu/transactions/encoding/farmers/${encodeURIComponent(state.farmer.id)}/livestock`, payload);
            const animal = unwrap(response);
            state.livestock.unshift(animal);
            renderLivestock();
            const valuation = latestValuation(animal);
            state.selected.set(animal.id, { livestock: animal, valuation, sellingPrice: valuation ? Number(valuation.estimated_value) : "", salePurpose: payload.sale_purpose });
            const addedCard = [...document.querySelectorAll(".tx-livestock-card")].find((node) => node.dataset.livestockId === String(animal.id));
            if (addedCard) { const checkbox = addedCard.querySelector(".tx-livestock-check"); if (checkbox) checkbox.checked = true; }
            renderSelected(); updateSummary();
            closeManualLivestock();
            $("tx-manual-livestock-form").reset();
            notice("New livestock record created and added to this transaction.");
        } catch (error) {
            errorBox.textContent = errorMessage(error);
            errorBox.hidden = false;
        } finally { button.disabled = false; }
    }

    function resetSelectedFarmerAndLivestock() {
        state.farmer = null;
        state.livestock = [];
        state.selected.clear();
        $("tx-farmer-search").value = "";
        $("tx-selected-farmer").hidden = true;
        $("tx-selected-farmer").textContent = "";
        $("tx-farmer-results").replaceChildren();
        $("tx-livestock-list").replaceChildren(el("p", "tx-muted", "Select a farmer to view eligible livestock."));
        renderSelected();
        updateSummary();
    }

    $("tx-auction-event").addEventListener("change", async () => {
        state.auctionSchedule = state.auctionSchedules.find((schedule) => String(schedule.id) === $("tx-auction-event").value) || null;
        resetSelectedFarmerAndLivestock();
        $("tx-farmer-search").disabled = !state.auctionSchedule;
        $("tx-add-livestock").disabled = !state.auctionSchedule || !state.farmer;
        const auctionBarangayId = state.auctionSchedule?.barangay_id || state.auctionSchedule?.auction_house?.barangay_id;
        if (auctionBarangayId) $("tx-selling-barangay").value = String(auctionBarangayId);
        if (state.auctionSchedule?.auction_date) $("tx-date").value = String(state.auctionSchedule.auction_date).slice(0, 10);
        if (state.auctionSchedule) {
            $("tx-farmer-search").focus();
            notice("Auction selected. Search for the farmer who sold livestock at this event.");
        }
    });


    let searchTimer;

    $("tx-farmer-search").addEventListener("input", () => {
        clearTimeout(searchTimer);

        // If the Encoder changes the query after selecting a farmer, clear the
        // previous selection and its livestock before showing new matches.
        if (state.farmer) {
            state.farmer = null;
            state.livestock = [];
            state.selected.clear();
            $("tx-selected-farmer").hidden = true;
            $("tx-selected-farmer").textContent = "";
            $("tx-livestock-list").replaceChildren(
                el("p", "tx-muted", "Select a farmer to view livestock."),
            );
            renderSelected();
            updateSummary();
        }

        searchTimer = setTimeout(searchFarmers, 300);
    });

    $("tx-add-livestock").addEventListener("click", async () => {
        if (!state.farmer || !state.auctionSchedule) return;
        try { await loadManualLivestockOptions(); $("tx-manual-livestock-dialog").showModal(); }
        catch (error) { notice(errorMessage(error), true); }
    });
    $("tx-manual-species").addEventListener("change", () => loadManualBreeds($("tx-manual-species").value).catch((e) => { $("tx-manual-error").textContent = errorMessage(e); $("tx-manual-error").hidden = false; }));
    $("tx-manual-livestock-form").addEventListener("submit", saveManualLivestock);
    $("tx-manual-close").addEventListener("click", closeManualLivestock);
    $("tx-manual-cancel").addEventListener("click", closeManualLivestock);
    $("tx-form").addEventListener("submit", saveDraft);
    $("tx-submit").addEventListener("click", submitDraft);

    const manilaDate = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Manila", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
    $("tx-date").max = manilaDate;
    $("tx-date").value = manilaDate;

    // Intentionally do not load farmers on page entry. The result panel stays
    // empty until the Encoder searches for a specific farmer.
    $("tx-farmer-results").replaceChildren();
    $("tx-auction-event-wrap").hidden = false;
    $("tx-farmer-search").disabled = true;
    $("tx-add-livestock").disabled = true;
    loadAuctionSchedules().catch((error) => notice(errorMessage(error), true));
}
