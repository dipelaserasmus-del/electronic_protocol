// R3c: the search box in the top bar (resources/views/search/box.blade.php). While typing: a few matching protocols
// from /search/suggest (only what the user may see), and the search words (φάκελος: …) offered as they are typed.
// ↑ ↓ choose, Enter opens the chosen protocol or the results page, Escape closes; "/" focuses the box.
import { fold } from './tags';

/** The search words (App\Services\Search\QueryParser) offered while typing their start; [word, what it does] */
export const OPERATORS = [
    ['αρ:', 'αριθμός ή από-έως'], ['έτος:', 'έτος ή «όλα»'], ['ημ:', 'ημερομηνία ή διάστημα (..)'],
    ['πότε:', 'σήμερα, χθες, εβδομάδα, 7μέρες, μήνας, πέρσι'], ['χρήστης:', 'ποιος το καταχώρισε'],   // R3i
    ['φάκελος:', 'κωδικός ή λέξη· «κανένας»'], ['θέμα:', 'στο θέμα'], ['από:', 'αρχή έκδοσης'], ['προς:', 'απευθύνεται'],
    ['εισ:', 'αρ. εισερχομένου (εισ:=120 μόνο το 120)'], ['σχετ:', 'σχετικοί αριθμοί'], ['λέξη:', 'λέξεις κλειδιά'], ['συνημμένο:', 'όνομα συνημμένου'],
    ['αδα:', 'ΑΔΑ'], ['ανάθεση:', 'χρήστης· «κανένας»'], ['κενό:', 'κενό πεδίο'], ['είναι:εκκρεμές', 'χωρίς φάκελο ή ΕΚΚΡΕΜΟΤΗΤΑ'],
    ['ημεισ:', 'ημ/νία εισερχομένου'], ['ημεξ:', 'ημ/νία εξερχομένου'], ['παρατηρήσεις:', 'παρατηρήσεις'],
];

/** Search words whose name starts with the last thing typed (at least 2 letters, no ":" yet) */
export function operatorHints(text) {
    const last = /(?:^|\s)([^\s:"]{2,})$/u.exec(text)?.[1];
    if (!last) return [];
    const f = fold(last);
    return OPERATORS.filter(([op]) => fold(op).startsWith(f) && fold(op) !== f);
}

function searchBox(url) {
    return {
        query: '', items: [], hints: [], more: false, open: false, loading: false, active: -1, timer: null, controller: null,
        init() { this.query = this.$refs.input.value; },
        get total() { return this.hints.length + this.items.length + (this.query ? 1 : 0); },
        allUrl() { return this.$root.action + '?q=' + encodeURIComponent(this.query); },
        typed() {
            this.query = this.$refs.input.value;
            this.hints = operatorHints(this.query);
            this.active = -1;
            this.open = this.query.trim().length > 0;
            // review of R3c: the protocols found for the earlier text go at once (↓ Enter must never open one of them)
            this.controller?.abort();
            this.items = []; this.more = false;
            this.loading = this.query.trim().length >= 2;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetch(), 250);
        },
        async fetch() {
            const q = this.query.trim();
            this.controller?.abort();
            if (q.length < 2) { this.items = []; this.more = false; return; }
            const controller = this.controller = new AbortController();
            this.loading = true;
            try {
                const r = await fetch(url + '?q=' + encodeURIComponent(q), {
                    headers: { Accept: 'application/json' }, signal: controller.signal, credentials: 'same-origin',
                });
                if (!r.ok) return;
                const data = await r.json();
                if (q !== this.query.trim()) return;   // typed on meanwhile
                this.items = data.items ?? []; this.more = !!data.more;
                if (this.active >= this.total) this.active = -1;
            } catch (e) {
                if (e.name !== 'AbortError') { this.items = []; }
            } finally { if (this.controller === controller) this.loading = false; }
        },
        useHint(h) {
            const input = this.$refs.input;
            input.value = this.query.replace(/[^\s:"]+$/u, '') + h[0];
            input.focus();
            this.typed();
        },
        close() { this.open = false; this.active = -1; },
        submitted() { this.close(); },
        key(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (!this.total) return;
                e.preventDefault();
                this.open = true;
                const n = this.total, down = e.key === 'ArrowDown';
                this.active = this.active < 0 ? (down ? 0 : n - 1) : (this.active + (down ? 1 : n - 1)) % n;
            } else if (e.key === 'Enter' && this.open && this.active >= 0) {
                if (this.active < this.hints.length) { e.preventDefault(); this.useHint(this.hints[this.active]); return; }
                const item = this.items[this.active - this.hints.length];
                if (item) { e.preventDefault(); window.location.href = item.url; }
                // the last entry ("Όλα τα αποτελέσματα") is the form's own submit
            } else if (e.key === 'Escape') {
                if (this.open) { e.preventDefault(); this.close(); } else this.$refs.input.blur();
            }
        },
    };
}

const register = () => window.Alpine.data('searchBox', searchBox);
if (window.Alpine) register();
document.addEventListener('alpine:init', register);

// "/" puts the cursor in the search box (as in Gmail), unless one is typing somewhere
document.addEventListener('keydown', e => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = e.target;
    if (t.closest?.('input, textarea, select, [contenteditable=""], [contenteditable="true"]')) return;
    const box = document.getElementById('app-search-q');
    if (!box) return;
    e.preventDefault();
    box.focus();
    box.select();
});
