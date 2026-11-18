// R1f: tags (Σχετικοί αριθμοί, Λέξεις κλειδιά) and the folder picker of the protocol form.
// Search ignores case and accents ("διαυγεια" finds "Διαύγεια"), like App\Support\TagList::fold on the server.

/** Lower case without accents, final ς = σ. Same rules as TagList::fold (shared cases: tests/fixtures/fold-cases.json). */
export function fold(text) {
    return String(text ?? '').normalize('NFD').replace(/\p{Mn}+/gu, '').toLowerCase().replace(/ς/g, 'σ');
}

const words = text => text.split(/[\s.,;:/()«»"'_-]+/).filter(Boolean);

/**
 * Items matching every typed word, best first: the whole text starts with what was typed, then a word starts with it,
 * then anywhere. Ties keep the list's order (keywords: most used first; folders: their usual order).
 * items: [[text, extra], …]; key(item) = the text searched.
 */
export function search(items, query, { limit = 8, exclude = [], key = it => it[0] } = {}) {
    const q = fold(query).trim();
    if (!q) return [];
    const parts = q.split(/\s+/);
    const skip = new Set(exclude.map(fold));
    const found = [];
    items.forEach((it, order) => {
        const f = fold(key(it));
        if (skip.has(fold(it[0])) || !parts.every(p => f.includes(p))) return;
        const score = f.startsWith(q) ? 0 : words(f).some(w => w.startsWith(parts[0])) ? 1 : 2;
        found.push([score, order, it]);
    });
    found.sort((a, b) => a[0] - b[0] || a[1] - b[1]);
    return found.slice(0, limit).map(f => f[2]);
}

const escape = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** The text (HTML-escaped) with the typed words marked. */
export function highlight(text, query) {
    text = String(text ?? '');
    const f = fold(text);
    const parts = fold(query).trim().split(/\s+/).filter(Boolean);
    if (!parts.length || f.length !== text.length) return escape(text);   // accents folded one-to-one, else no marks
    const marked = new Array(text.length).fill(false);
    for (const p of parts) {
        for (let i = f.indexOf(p); i !== -1; i = f.indexOf(p, i + 1)) marked.fill(true, i, i + p.length);
    }
    let out = '';
    for (let i = 0; i < text.length; i++) {
        if (marked[i] && !marked[i - 1]) out += '<mark>';
        out += escape(text[i]);
        if (marked[i] && !marked[i + 1]) out += '</mark>';
    }
    return out;
}

const readJson = id => {
    try { return JSON.parse(document.getElementById(id)?.textContent || '[]'); } catch { return []; }
};

/**
 * A tag box: x-data="tagBox('keywords', true)". The pills come from the server (data-tag, one per tag); this handles
 * typing: Enter / comma / leaving the box adds, a click on a pill's text edits it in place, Backspace in the empty box
 * removes the last one, suggestions (keywords) with ↑ ↓ Enter or the mouse.
 */
function tagBox(field, withSuggestions = false) {
    return {
        list: [], items: [], active: -1, editing: null, query: '', target: null, targetIndex: null,
        init() { if (withSuggestions) this.list = readJson('suggest-' + field); },
        current() { return [...this.$root.querySelectorAll('[data-tag]')].map(el => el.dataset.tag); },
        canonical(text) {   // a known keyword typed with other case/accents takes the known spelling
            const f = fold(text.trim());
            const hit = f && this.list.find(([k]) => fold(k) === f);
            return hit ? hit[0] : text.trim();
        },
        suggest(input, index = null) {
            if (!this.list.length) return;
            this.target = input; this.targetIndex = index; this.query = input.value;
            const others = this.current().filter((_, i) => i !== index);
            this.items = search(this.list, input.value, { exclude: others });
            this.active = -1;
        },
        mark(text) { return highlight(text, this.query); },
        close() { this.items = []; this.active = -1; },
        commit(input, index = null) {
            let value = input.value.trim();
            input.value = ''; this.close();
            if (this.list.length) value = value.split(/[,;]/).map(p => this.canonical(p)).filter(Boolean).join(', ');
            if (index === null) {
                if (value) this.$wire.addTag(field, value);
            } else {
                this.editing = null;
                if (value !== this.current()[index]) this.$wire.editTag(field, index, value);
            }
        },
        pick(text) {
            const input = this.target;
            if (!input) return;
            input.value = text;
            this.commit(input, this.targetIndex);
            input.focus();
        },
        key(e, index = null) {
            const input = e.target;
            if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && this.items.length) {
                e.preventDefault();
                const n = this.items.length;
                const down = e.key === 'ArrowDown';
                this.active = this.active < 0 ? (down ? 0 : n - 1) : (this.active + (down ? 1 : n - 1)) % n;
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const chosen = this.items[this.active];   // none once the list was closed (review of R1f)
                if (chosen) this.pick(chosen[0]); else this.commit(input, index);
            } else if (e.key === ',' || e.key === ';') {
                e.preventDefault();
                this.commit(input, index);
            } else if (e.key === 'Escape') {
                if (this.items.length) { e.preventDefault(); this.close(); }
                else if (index !== null) { e.preventDefault(); this.editing = null; }
            } else if (e.key === 'Backspace' && index === null && input.value === '') {
                const n = this.current().length;
                if (n) { e.preventDefault(); this.$wire.removeTag(field, n - 1); }
            }
        },
        typed(e, index = null) {
            if (index === null && /[,;]/.test(e.target.value)) this.commit(e.target);   // pasted list
            else this.suggest(e.target, index);
        },
        left(e, index = null) {
            if (index === null) this.commit(e.target);
            else if (this.editing === index) this.commit(e.target, index);   // Escape already cancelled otherwise
        },
        edit(index) {
            this.editing = index;
            this.$nextTick(() => {
                const input = this.$root.querySelector(`[data-edit="${index}"]`);
                if (!input) return;
                input.value = this.current()[index] ?? '';
                input.focus();
                input.select();
            });
        },
    };
}

/**
 * The folder picker: x-data="folderPicker". Typing filters the folders by code and description; ↑ ↓ Enter (or Tab, or
 * a click) chooses. The chosen code goes to form.fakelos (live: the retention "Διατήρηση" follows the folder).
 */
function folderPicker() {
    return {
        folders: [], open: false, typed: false, query: '', active: 0,
        init() { this.folders = readJson('suggest-folders'); },
        get value() { return this.$wire.form?.fakelos ?? ''; },
        label() {
            const f = this.folders.find(f => f[0] === this.value);
            return f ? (f[1] ? `${f[0]} · ${f[1]}` : f[0]) : this.value;
        },
        get items() {
            if (!this.typed) return this.folders;
            return search(this.folders, this.query, { limit: 100, key: f => `${f[0]} ${f[0].replace(/^φ\.?/i, '')} ${f[1]}` });
        },
        mark(text) { return this.typed ? highlight(text, this.query) : escape(String(text ?? '')); },
        show() {
            if (this.open) return;
            this.open = true; this.typed = false; this.query = '';
            this.active = Math.max(0, this.folders.findIndex(f => f[0] === this.value));
            this.$nextTick(() => this.scroll());
        },
        close() { this.open = false; this.typed = false; this.query = ''; },
        input(e) { this.open = true; this.typed = true; this.query = e.target.value; this.active = 0; },
        choose(folder) {
            if (folder && folder[0] !== this.value) this.$wire.set('form.fakelos', folder[0]);
            this.close();
        },
        scroll() { this.$root.querySelector('[data-folder-active]')?.scrollIntoView({ block: 'nearest' }); },
        key(e) {
            const n = this.items.length;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!this.open) { this.show(); return; }
                if (n) this.active = (this.active + (e.key === 'ArrowDown' ? 1 : n - 1)) % n;
                this.$nextTick(() => this.scroll());
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.open && n) this.choose(this.items[this.active]); else this.show();
            } else if (e.key === 'Tab') {
                if (this.open && this.typed && n) this.choose(this.items[this.active]); else this.close();
            } else if (e.key === 'Escape') {
                if (this.open) { e.preventDefault(); this.close(); }
            }
        },
    };
}

const register = () => {
    window.Alpine.data('tagBox', tagBox);
    window.Alpine.data('folderPicker', folderPicker);
};
if (window.Alpine) register();
document.addEventListener('alpine:init', register);

window.appFold = fold;   // the browser tests check fold() against the server's cases
