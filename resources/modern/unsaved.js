// R1k (owner, 2026-11-20): unsaved changes on the protocol page. The form's values when the page opened (= the last
// save: a save reloads the page) are kept; while they differ, Αποθήκευση has a red dot, and leaving the page asks
// whether to save, listing what changed. <form x-data="unsavedChanges({ labels, users })">; the dialog is #unsaved-dialog.
const clone = (v) => JSON.parse(JSON.stringify(v ?? null));

function unsavedChanges({ labels = {}, users = {} } = {}) {
    return {
        progress: null,   // upload in progress (0–100) or null; the save buttons wait for it (review of R1c)
        start: null,
        leaving: false,   // a save or a confirmed leave is under way: no question
        target: null,

        init() {
            this.start = this.state();
            // the browser's own question when the tab is closed or reloaded
            window.addEventListener('beforeunload', (e) => {
                if (this.leaving || !this.changes().length) return;
                e.preventDefault();
                e.returnValue = '';
            });
            // links that leave the page (top bar, Προηγούμενο / Επόμενο …): our question, with the changes
            document.addEventListener('click', (e) => this.click(e), true);
            this.$el.addEventListener('submit', () => { this.leaving = true; });
            this.$el.addEventListener('input', () => { this.leaving = false; }, true);   // a save that stayed (errors)
            window.Livewire?.hook?.('commit', ({ succeed, fail }) => {
                fail(() => { this.leaving = false; });
                succeed(({ effects }) => { if (!effects?.redirect) this.leaving = false; });
            });
        },

        /** what the user can change: the fields, the assignments, the new files */
        state() {
            const w = this.$wire;
            return clone({ form: w.form, handle: w.handle, inform: w.inform, uploads: (w.uploads ?? []).length, adas: w.uploadAdas, newAda: w.newAda, noStamp: w.noStamp });
        },

        /** list of [label, before, now] */
        changes() {
            if (!this.start) return [];
            const now = this.state();
            const out = [];
            const text = (v) => (v === null || v === undefined || v === '' ? '—' : String(v));
            for (const [key, value] of Object.entries(now.form ?? {})) {
                const before = this.start.form?.[key] ?? '';
                if (String(before ?? '').trim() !== String(value ?? '').trim()) out.push([labels[key] ?? key, text(before), text(value)]);
            }
            const names = (ids) => (ids ?? []).map((id) => users[id] ?? id).join(', ');
            const sameIds = (a, b) => [...(a ?? [])].map(String).sort().join() === [...(b ?? [])].map(String).sort().join();
            if (!sameIds(this.start.handle, now.handle)) out.push([labels.handle ?? 'Διεκπεραίωση', text(names(this.start.handle)), text(names(now.handle))]);
            if (!sameIds(this.start.inform, now.inform)) out.push([labels.inform ?? 'Ενημέρωση', text(names(this.start.inform)), text(names(now.inform))]);
            if (now.uploads !== this.start.uploads) out.push([labels.uploads ?? 'Νέα συνημμένα', text(this.start.uploads), text(now.uploads)]);
            if (JSON.stringify(now.adas) !== JSON.stringify(this.start.adas) || (now.newAda ?? '') !== (this.start.newAda ?? '')) {
                out.push([labels.ada ?? 'ΑΔΑ', '—', text([...(now.adas ?? []), now.newAda].filter(Boolean).join(', '))]);
            }
            if (now.noStamp !== this.start.noStamp) out.push([labels.noStamp ?? 'Χωρίς σφραγίδα', now.noStamp ? 'όχι' : 'ναι', now.noStamp ? 'ναι' : 'όχι']);
            return out;
        },

        get dirty() { return this.changes().length > 0; },

        click(e) {
            const a = e.target.closest?.('a[href]');
            if (!a || this.leaving || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            if (a.target === '_blank' || a.hasAttribute('download') || a.closest('#unsaved-dialog')) return;
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            const url = new URL(a.href, location.href);
            if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search)) return;
            if (!this.dirty) return;
            e.preventDefault();
            e.stopPropagation();
            this.target = url.pathname + url.search + url.hash;   // a path: the server accepts only these
            bootstrap.Modal.getOrCreateInstance(document.getElementById('unsaved-dialog')).show();
        },

        /** the dialog's buttons */
        saveAndGo() {
            this.leaving = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('unsaved-dialog')).hide();
            this.$wire.save(this.target);
        },
        discardAndGo() {
            this.leaving = true;
            location.href = this.target;
        },
    };
}

const register = () => window.Alpine.data('unsavedChanges', unsavedChanges);
if (window.Alpine) register();
document.addEventListener('alpine:init', register);
