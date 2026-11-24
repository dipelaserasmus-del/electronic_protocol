// R1k (owner, 2026-11-20): unsaved changes on the protocol page. The form's values when the page opened (= the last
// save: a save reloads the page) are kept; while they differ, Αποθήκευση has a red dot, and leaving the page asks
// whether to save, listing what changed. <form x-data="unsavedChanges({ labels, users })">; the dialog is #unsaved-dialog.
const clone = (v) => JSON.parse(JSON.stringify(v ?? null));

function unsavedChanges({ labels = {}, users = {}, enabled = true } = {}) {
    return {
        tick: 0,   // typing in a tag box (not in $wire until Enter / leaving it): count again
        progress: null,   // upload in progress (0–100) or null; the save buttons wait for it (review of R1c)
        start: null,
        leaving: false,   // a save or a confirmed leave is under way: no question
        target: null,

        init() {
            // review of R1k: only where the page saves (the dialog's Αποθήκευση); e.g. an assigner who may only assign
            // uses Ανάθεση, as before
            if (!enabled || !document.getElementById('unsaved-dialog')) return;
            this.form = this.$el;   // $el is the element asking (e.g. the dot), not the form
            this.start = this.state();
            const again = () => { this.tick++; };
            this.$el.addEventListener('input', again, true);
            this.$el.addEventListener('tag-pending', again);
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
            const form = clone(w.form) ?? {};
            // review of R1k: fields sent on blur / change (wire:model.blur, .change) are not in $wire.form while the user is
            // still in them: take what they show
            (this.form ?? this.$el).querySelectorAll('input, textarea, select').forEach((el) => {
                if (el.type === 'checkbox' || el.type === 'radio' || el.type === 'file' || el.multiple) return;
                const attr = [...el.attributes].find((a) => a.name.startsWith('wire:model') && a.value.startsWith('form.'));
                if (attr) form[attr.value.slice(5)] = el.value;
            });
            return clone({ form, handle: w.handle, inform: w.inform, uploads: (w.uploads ?? []).length, adas: w.uploadAdas, newAda: w.newAda });
        },

        /** list of [label, before, now] */
        changes() {
            if (!this.start) return [];
            void this.tick;
            const now = this.state();
            const out = [];
            const pendingTags = {};   // field => text typed / on its way, merged into that field's row (review of R1k)
            // review of R1k: a tag typed but not added yet, or on its way to the server
            (this.form ?? this.$el).querySelectorAll('[x-data^="tagBox("]').forEach((box) => {
                const field = /tagBox\('([^']+)'/.exec(box.getAttribute('x-data'))?.[1];
                const typed = [...box.querySelectorAll('input.tag-input')].map((i) => i.value.trim()).filter(Boolean);
                // a tag being edited in place, changed but not yet sent (review of R1k)
                const tags = [...box.querySelectorAll('[data-tag]')].map((el) => el.dataset.tag);
                box.querySelectorAll('input.tag-edit').forEach((i) => {
                    const value = i.value.trim();
                    if (i.offsetParent !== null && value !== (tags[Number(i.dataset.edit)] ?? '')) typed.push(value || '(διαγραφή)');
                });
                // a request still running, also a deletion (empty text: review of R1k)
                if (box.dataset.pending !== undefined) typed.push(box.dataset.pending || '(διαγραφή)');
                if (typed.length) pendingTags[field] = typed.join(', ') + ' (νέο)';
            });
            const text = (v) => (v === null || v === undefined || v === '' ? '—' : String(v));
            for (const [key, value] of Object.entries(now.form ?? {})) {
                const before = this.start.form?.[key] ?? '';
                const changed = String(before ?? '').trim() !== String(value ?? '').trim();
                if (changed || pendingTags[key]) {
                    // one row per field: what is saved-to-be and what is still typed (review of R1k)
                    const shown = [changed || String(value ?? '').trim() ? text(value) : '', pendingTags[key] ?? ''].filter(Boolean).join(' + ');
                    out.push([labels[key] ?? key, text(before), shown]);
                }
            }
            // a file still uploading (review of R1k)
            if (this.progress !== null) out.push([labels.uploads ?? 'Νέα συνημμένα', '—', `ανεβαίνει… ${this.progress}%`]);
            const names = (ids) => (ids ?? []).map((id) => users[id] ?? id).join(', ');
            const sameIds = (a, b) => [...(a ?? [])].map(String).sort().join() === [...(b ?? [])].map(String).sort().join();
            if (!sameIds(this.start.handle, now.handle)) out.push([labels.handle ?? 'Διεκπεραίωση', text(names(this.start.handle)), text(names(now.handle))]);
            if (!sameIds(this.start.inform, now.inform)) out.push([labels.inform ?? 'Ενημέρωση', text(names(this.start.inform)), text(names(now.inform))]);
            if (now.uploads !== this.start.uploads) out.push([labels.uploads ?? 'Νέα συνημμένα', text(this.start.uploads), text(now.uploads)]);
            if (JSON.stringify(now.adas) !== JSON.stringify(this.start.adas) || (now.newAda ?? '') !== (this.start.newAda ?? '')) {
                out.push([labels.ada ?? 'ΑΔΑ', '—', text([...(now.adas ?? []), now.newAda].filter(Boolean).join(', '))]);
            }
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
            const dialog = document.getElementById('unsaved-dialog');
            if (!dialog || !this.dirty) return;
            e.preventDefault();
            e.stopPropagation();
            this.target = url.pathname + url.search + url.hash;   // a path: the server accepts only these
            bootstrap.Modal.getOrCreateInstance(dialog).show();
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
