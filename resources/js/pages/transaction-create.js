import api from "../api";
import { formatDate as sharedFormatDate } from "../utils/date-format";

function initializeTransactionPage() {
    const root = document.getElementById("sale-page");
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = "1";

    const $ = (id) => document.getElementById(id);
    const form = $("sale-form"), itemsContainer = $("sale-items"), alertBox = $("sale-alert");
    const saveButton = $("save-draft"), submitButton = $("submit-sale"), addButton = $("add-item");
    const nextButton = $("sale-next-step"), backButton = $("sale-back-step");
    const totalElement = $("sale-total"), reviewElement = $("sale-review");
    const money = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
    const state = { livestock: [], barangays: [], rows: new Map(), draftId: null, step: 1, busy: false, submitted: false, uncertain: false, nextRowId: 0 };

    const unwrap = (r) => r.data?.data ?? r.data;
    const collection = (p) => Array.isArray(p) ? p : (Array.isArray(p?.data) ? p.data : []);
    const errorMessage = (e) => {
        const errors = e?.response?.data?.errors;
        return errors ? Object.values(errors).flat().join(" ") : (e?.response?.data?.message || e?.message || "Something went wrong.");
    };
    function alert(message, type = "error") { alertBox.textContent = message; alertBox.className = `sale-alert ${type}`; alertBox.hidden = false; }
    function clearAlert() { alertBox.hidden = true; alertBox.textContent = ""; }
    function setBusy(busy) { state.busy = busy; [saveButton, submitButton, nextButton, backButton, addButton].forEach((b) => { if (b) b.disabled = busy || state.uncertain || state.submitted; }); }

    function updateTotal() {
        const total = [...state.rows.values()].reduce((sum, row) => sum + Math.max(0, Number(row.price.value) || 0), 0);
        totalElement.textContent = money.format(total);
    }
    function usedIds(except = null) { return new Set([...state.rows.entries()].filter(([id]) => id !== except).map(([, row]) => row.livestock.value).filter(Boolean)); }
    function refreshOptions() {
        for (const [id, row] of state.rows) {
            const previous = row.livestock.value, used = usedIds(id);
            row.livestock.replaceChildren(new Option("Choose livestock", ""));
            state.livestock.forEach((animal) => {
                if (used.has(String(animal.id))) return;
                const species = animal.species?.name || animal.species_name || "Livestock";
                const breed = animal.breed?.name || animal.breed_name || "";
                const suffix = animal.tag_number || String(animal.id).slice(0, 8);
                const auctionLabel = animal.auction_participation_status === "registered" ? "Auction registered" : "";
                row.livestock.add(new Option([species, breed, suffix, auctionLabel].filter(Boolean).join(" · "), String(animal.id)));
            });
            if ([...row.livestock.options].some((o) => o.value === previous)) row.livestock.value = previous;
        }
        updateAuctionWarning();
    }
    function addRow(prefill = null) {
        if (state.rows.size >= 50) return;
        const id = ++state.nextRowId;
        const element = document.createElement("article");
        element.className = "sale-item";
        element.innerHTML = `
            <div class="sale-item-header"><strong>Livestock <span class="sale-item-number"></span></strong><button type="button" class="sale-remove" aria-label="Remove livestock">Remove</button></div>
            <div class="sale-grid">
                <label class="sale-span-2">Which animal did you sell?<select class="sale-livestock" required><option value="">Choose livestock</option></select></label>
                <label>How much did you receive? (₱)<input class="sale-price" type="number" min="0.01" max="999999999999.99" step="0.01" placeholder="e.g. 25000" required></label>
                <label>What was it sold for?<select class="sale-purpose" required><option value="slaughter">Slaughter</option><option value="breeding">Breeding</option></select></label>
            </div>`;
        const row = { element, livestock: element.querySelector(".sale-livestock"), price: element.querySelector(".sale-price"), purpose: element.querySelector(".sale-purpose"), remove: element.querySelector(".sale-remove") };
        state.rows.set(id, row); itemsContainer.appendChild(element);
        row.livestock.addEventListener("change", refreshOptions);
        row.price.addEventListener("input", updateTotal);
        row.remove.addEventListener("click", () => { if (state.rows.size <= 1) { alert("Keep at least one livestock item in your sale."); return; } state.rows.delete(id); element.remove(); renumber(); refreshOptions(); updateTotal(); });
        const preselectedLivestockId = prefill?.livestock_id ? String(prefill.livestock_id) : "";
        if (prefill) { row.price.value = prefill.actual_selling_price || ""; row.purpose.value = prefill.sale_purpose || "slaughter"; }
        refreshOptions();
        // Set the selected ID only after its option has been added to the select.
        if (preselectedLivestockId && [...row.livestock.options].some((option) => option.value === preselectedLivestockId)) {
            row.livestock.value = preselectedLivestockId;
        }
        renumber(); updateTotal(); updateAuctionWarning();
    }
    function renumber() { [...state.rows.values()].forEach((r, i) => { r.element.querySelector(".sale-item-number").textContent = `#${i + 1}`; }); }
    async function loadBarangays() {
        const profileResponse = await api.get('/farmer/profile');
        const profile = unwrap(profileResponse);
        const municipalityId = profile?.municipality_id;
        const homeBarangayId = profile?.barangay_id;
        if (!municipalityId) throw new Error('Your municipality could not be identified. Please update your farmer profile first.');
        const response = await api.get('/gis/barangays', { params: { municipality_id: municipalityId } });
        state.barangays = collection(unwrap(response));
        const select = form.elements.selling_barangay_id;
        select.replaceChildren(new Option('Choose barangay', ''));
        state.barangays.forEach((barangay) => select.add(new Option(barangay.name, String(barangay.id))));
        if (homeBarangayId && state.barangays.some((b) => String(b.id) === String(homeBarangayId))) select.value = String(homeBarangayId);
    }
    async function loadLivestock() {
        const response = await api.get("/farmer/livestock", { params: { status: "available" } });
        state.livestock = collection(unwrap(response)).filter((a) => String(a.status || "").toLowerCase() === "available");
        refreshOptions();
        if (!state.livestock.length) alert("You don't have available registered livestock yet. Please register your livestock first.");
    }
    function selectedAuctionRegisteredAnimals() {
        return [...state.rows.values()]
            .map((row) => state.livestock.find((animal) => String(animal.id) === String(row.livestock.value)))
            .filter((animal) => animal?.auction_participation_status === "registered");
    }
    function updateAuctionWarning() {
        const warning = $("sale-auction-warning");
        const animals = selectedAuctionRegisteredAnimals();
        if (!warning) return;
        warning.hidden = animals.length === 0;
        if (animals.length) {
            warning.innerHTML = `<strong>Some selected livestock are registered in an auction.</strong> You can still record this private sale. Once you submit it, the livestock will be removed from the auction list while the LGU Authority reviews the transaction.`;
        }
    }

    function validateStep(step) {
        clearAlert();
        if (step === 1) {
            if (!state.rows.size) { alert("Please add at least one livestock."); return false; }
            for (const row of state.rows.values()) {
                if (!row.livestock.value || !row.price.value || Number(row.price.value) <= 0 || !row.purpose.value) { alert("Please select each livestock, enter its selling price, and choose its sale purpose."); return false; }
            }
        }
        if (step === 2) {
            const date = form.elements.transaction_date, location = form.elements.selling_barangay_id;
            if (!date.value || date.value > new Date().toLocaleDateString("en-CA")) { alert("Please enter a valid sale date. Future dates are not allowed."); date.focus(); return false; }
            if (!location.value) { alert("Please choose the barangay where the sale happened."); location.focus(); return false; }
        }
        if (step === 3) {
            const phone = form.elements.buyer_phone.value.trim();
            if (phone && !/^(09\d{9}|\+639\d{9})$/.test(phone)) { alert("Use a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX), or leave it blank."); form.elements.buyer_phone.focus(); return false; }
        }
        return true;
    }
    function renderReview() {
        const items = [...state.rows.values()].map((r) => {
            const animal = state.livestock.find((a) => String(a.id) === r.livestock.value);
            const name = [animal?.species?.name || animal?.species_name || "Livestock", animal?.breed?.name || animal?.breed_name].filter(Boolean).join(" · ");
            return `<div class="sale-review-row"><div><strong>${escapeHtml(name)}</strong><small>${r.purpose.value === "slaughter" ? "Slaughter" : "Breeding"}</small></div><strong>${money.format(Number(r.price.value))}</strong></div>`;
        }).join("");
        const date = form.elements.transaction_date.value;
        reviewElement.innerHTML = `<div class="sale-review-group"><h3>Livestock sold</h3>${items}<div class="sale-review-row sale-review-total"><strong>Total received</strong><strong>${totalElement.textContent}</strong></div></div>
            <div class="sale-review-group"><h3>Sale details</h3><p><strong>Date:</strong> ${escapeHtml(date)}</p><p><strong>Where:</strong> ${escapeHtml(form.elements.selling_barangay_id.selectedOptions[0]?.textContent || "Not selected")}</p></div>
            <div class="sale-review-group"><h3>Buyer information</h3><p><strong>Name:</strong> ${escapeHtml(form.elements.buyer_name.value.trim() || "Not provided")}</p><p><strong>Mobile:</strong> ${escapeHtml(form.elements.buyer_phone.value.trim() || "Not provided")}</p></div>`;
    }
    function escapeHtml(value) { return String(value).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
    function showStep(step) {
        state.step = step;
        root.querySelectorAll(".sale-step").forEach((section) => { const active = Number(section.dataset.step) === step; section.hidden = !active; section.classList.toggle("active", active); });
        root.querySelectorAll(".sale-progress-step").forEach((node) => { const n = Number(node.dataset.progress); node.classList.toggle("active", n === step); node.classList.toggle("complete", n < step); });
        backButton.hidden = step === 1; nextButton.hidden = step === 4; submitButton.hidden = step !== 4; saveButton.hidden = false;
        if (step === 4) renderReview();
        root.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    function buildPayload() {
        return {
            buyer_name: form.elements.buyer_name.value.trim() || null,
            buyer_phone: form.elements.buyer_phone.value.trim() || null,
            transaction_date: form.elements.transaction_date.value,
            selling_barangay_id: form.elements.selling_barangay_id.value,
            remarks: form.elements.remarks.value.trim() || null,
            items: [...state.rows.values()].map((r) => ({ livestock_id: r.livestock.value, actual_selling_price: Number(r.price.value), sale_purpose: r.purpose.value }))
        };
    }
    async function saveDraft() {
        if (!validateStep(1) || !validateStep(2) || !validateStep(3)) return;
        const payload = buildPayload();
        const response = state.draftId ? await api.patch(`/farmer/transactions/${encodeURIComponent(state.draftId)}`, payload) : await api.post("/farmer/transactions", payload);
        const transaction = unwrap(response);
        if (!transaction?.id) throw new Error("The draft could not be confirmed by the server.");
        state.draftId = transaction.id; saveButton.textContent = "Save changes";
    }
    async function submitSale(event) {
        event.preventDefault(); if (state.busy || state.submitted || state.uncertain) return;
        if (![1,2,3].every(validateStep)) return;
        const auctionAnimals = selectedAuctionRegisteredAnimals();
        if (auctionAnimals.length) {
            const names = auctionAnimals.map((animal) => `${animal.species?.name || animal.species_name || "Livestock"} (${String(animal.id).slice(0, 8)})`).join(", ");
            const confirmed = window.confirm(`The following livestock are currently registered for an auction: ${names}.

Submitting this sale will immediately remove these animals from the auction list while the LGU Authority reviews the transaction. If the sale is rejected and the auction is still open, the registration can be restored. Continue?`);
            if (!confirmed) return;
        }
        setBusy(true);
        try {
            await saveDraft();
            await api.post(`/farmer/transactions/${encodeURIComponent(state.draftId)}/submit`);
            state.submitted = true;
            form.innerHTML = `<div class="sale-success"><div class="sale-success-icon">✓</div><h2>Your sale has been recorded!</h2><p>Your transaction has been sent to the Municipal Agriculture Office for verification. You can check its progress in My Transactions.</p><span class="sale-status">Pending Verification</span><a class="sale-btn sale-btn-primary" href="/transactions">View my transactions</a></div>`;
        } catch (e) { alert(errorMessage(e)); }
        finally { setBusy(false); }
    }
    async function loadDraft() {
        const id = new URLSearchParams(location.search).get("draft"); if (!id) return;
        const response = await api.get(`/farmer/transactions/${encodeURIComponent(id)}`), draft = unwrap(response);
        if (draft.status !== "draft" || draft.source !== "farmer") throw new Error("Only your own saved draft can be edited.");
        state.draftId = draft.id;
        form.elements.buyer_name.value = draft.buyer_name || ""; form.elements.buyer_phone.value = draft.buyer_phone || "";
        form.elements.transaction_date.value = draft.transaction_date ? String(draft.transaction_date).slice(0,10) : "";
        form.elements.selling_barangay_id.value = draft.selling_barangay_id || draft.selling_barangay?.id || form.elements.selling_barangay_id.value || ""; form.elements.remarks.value = draft.remarks || "";
        state.rows.clear(); itemsContainer.replaceChildren();

        // A saved draft reserves its livestock against duplicate transactions.
        // The normal available-livestock endpoint may therefore omit those animals.
        // Rehydrate the exact livestock attached to this draft before rebuilding rows,
        // otherwise the select cannot restore the saved livestock_id.
        const draftItems = Array.isArray(draft.items) ? draft.items : [];
        const knownLivestockIds = new Set(state.livestock.map((animal) => String(animal.id)));
        draftItems.forEach((item) => {
            const animal = item.livestock || item.livestock_record || null;
            if (animal?.id && !knownLivestockIds.has(String(animal.id))) {
                state.livestock.push(animal);
                knownLivestockIds.add(String(animal.id));
            }
        });

        draftItems.forEach((item) => addRow(item));
        if (!state.rows.size) addRow();
        refreshOptions();
        saveButton.hidden = false; saveButton.textContent = "Save changes"; showStep(1); alert("Your saved sale has been reopened. Continue editing and submit when ready.", "success");
    }

    addButton.addEventListener("click", () => addRow());
    nextButton.addEventListener("click", () => { if (validateStep(state.step)) showStep(Math.min(4, state.step + 1)); });
    backButton.addEventListener("click", () => showStep(Math.max(1, state.step - 1)));
    saveButton.addEventListener("click", async () => { if (state.busy) return; setBusy(true); try { await saveDraft(); alert("Your sale has been saved. You can continue later from My Transactions.", "success"); } catch(e) { alert(errorMessage(e)); } finally { setBusy(false); } });
    form.addEventListener("submit", (event) => {
        // Enter advances the guided form instead of unexpectedly saving/submitting.
        // Only the final review step may submit the transaction.
        if (state.step < 4) {
            event.preventDefault();
            if (validateStep(state.step)) showStep(Math.min(4, state.step + 1));
            return;
        }
        submitSale(event);
    });

    (async () => {
        const date = form.elements.transaction_date; date.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0,10); date.max = date.value;
        addRow();
        try { await loadBarangays(); await loadLivestock(); await loadDraft(); } catch(e) { alert(errorMessage(e)); }
    })();
}

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initializeTransactionPage, { once: true });
else initializeTransactionPage();
