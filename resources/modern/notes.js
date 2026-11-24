// N05: each user's notepad (partials/notepad.blade.php, App\Http\Controllers\NotesController). A tab on the right edge
// of every page opens a side panel (open / closed is remembered per browser). The note is plain text with a little
// markdown, shown formatted: # headings make foldable sections, "- [ ]" to-dos are ticked with a click, **bold**,
// *italic*, `code`, lists and [links](https://…). Everything is escaped first; only http, https and mailto links are
// made. It saves itself about a second after typing stops; a save from an older copy (another tab) is not allowed to
// overwrite a newer one silently: the user chooses.

const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
export const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ESC[c]);

/** an http(s) / mailto address, or null */
export function safeUrl(raw) {
    const text = String(raw).trim();
    if (!/^(https?:\/\/|mailto:)/i.test(text)) return null;
    try {
        const url = new URL(text);
        return ['http:', 'https:', 'mailto:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
}

/** one line's inline formatting; the text is escaped before anything else */
export function inline(text) {
    const links = [];
    // links first, on the raw text (the address must not be escaped twice); a placeholder keeps them out of the rest
    let out = String(text).replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, (m, label, url) => {
        const href = safeUrl(url);
        links.push(href
            ? `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`
            : escapeHtml(m));
        return `\u0000${links.length - 1}\u0000`;
    });
    out = escapeHtml(out)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*\w])\*([^*\s][^*]*)\*(?!\w)/g, '$1<em>$2</em>')
        .replace(/(^|[^\w])_([^_\s][^_]*)_(?!\w)/g, '$1<em>$2</em>');
    return out.replace(/\u0000(\d+)\u0000/g, (m, i) => links[Number(i)]);
}

/** the whole note as HTML: sections by heading, to-dos with their line number (data-line) */
export function render(text, folded = {}) {
    const lines = String(text ?? '').replace(/\r\n?/g, '\n').split('\n');
    let html = '';
    let list = null;   // 'ul' | 'ol' | null
    let para = [];
    let inSection = false;
    const closePara = () => { if (para.length) { html += `<p>${para.join('<br>')}</p>`; para = []; } };
    const closeList = () => { if (list) { html += `</${list}>`; list = null; } };
    const openList = (kind, cls = '') => { closePara(); if (list !== kind) { closeList(); html += `<${kind}${cls}>`; list = kind; } };
    lines.forEach((line, n) => {
        let m;
        if ((m = /^(#{1,3})\s+(.*)$/.exec(line))) {
            closePara(); closeList();
            if (inSection) html += '</div></details>';
            const title = m[2].trim();
            const open = folded[title] ? '' : ' open';
            html += `<details class="note-section note-h${m[1].length}"${open} data-title="${escapeHtml(title)}"><summary>${inline(title)}</summary><div class="note-section-body">`;
            inSection = true;
        } else if ((m = /^\s*[-*]\s+\[([ xX])\]\s?(.*)$/.exec(line))) {
            openList('ul', ' class="note-todos"');
            const done = m[1] !== ' ';
            html += `<li class="note-todo${done ? ' done' : ''}"><label><input type="checkbox" class="form-check-input" data-line="${n}"${done ? ' checked' : ''}> <span>${inline(m[2])}</span></label></li>`;
        } else if ((m = /^\s*[-*]\s+(.*)$/.exec(line))) {
            openList('ul');
            html += `<li>${inline(m[1])}</li>`;
        } else if ((m = /^\s*\d+[.)]\s+(.*)$/.exec(line))) {
            openList('ol');
            html += `<li>${inline(m[1])}</li>`;
        } else if (line.trim() === '') {
            closePara(); closeList();
        } else {
            closeList();
            para.push(inline(line));
        }
    });
    closePara(); closeList();
    if (inSection) html += '</div></details>';
    return html;
}

/** ticks / unticks the to-do on line n */
export function toggleTodo(text, n) {
    const lines = String(text).split('\n');
    lines[n] = (lines[n] ?? '').replace(/^(\s*[-*]\s+\[)([ xX])(\])/, (m, a, mark, b) => a + (mark === ' ' ? 'x' : ' ') + b);
    return lines.join('\n');
}

const store = {
    get: (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } },
    set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private mode */ } },
};

function notepad({ url }) {
    return {
        open: store.get('notes.open', false),
        loaded: false,
        mode: 'view',
        body: '',
        saved: '',       // what the server has (to know whether there are changes)
        savedAt: '',     // HH:MM of the last save
        version: 0,
        status: '',
        conflict: null,  // {body, version, saved}
        timer: null,
        saving: false,
        folded: store.get('notes.folded', {}),

        init() {
            if (this.open) this.load();
            this.$watch('open', (v) => { store.set('notes.open', v); if (v && !this.loaded) this.load(); });
            // back to this tab: a newer copy saved elsewhere is shown (or, with unsaved changes here, the choice)
            document.addEventListener('visibilitychange', () => { if (!document.hidden && this.open && this.loaded) this.check(); });
            window.addEventListener('beforeunload', () => { if (this.timer) this.save(); });
        },
        get html() { return render(this.body, this.folded); },
        get dirty() { return this.body !== this.saved; },

        async request(method, data) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const r = await fetch(url, {
                method, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: data ? JSON.stringify(data) : undefined,
            });
            return { status: r.status, data: await r.json().catch(() => ({})) };
        },
        async load() {
            try {
                const { data } = await this.request('GET');
                this.body = this.saved = data.body ?? '';
                this.version = data.version ?? 0;
                this.savedAt = data.saved ?? '';
                this.status = this.savedAt ? `Αποθηκεύτηκε ${this.savedAt}` : '';
                this.mode = this.body.trim() === '' ? 'edit' : 'view';
                this.loaded = true;
            } catch {
                this.status = 'Οι σημειώσεις δεν φορτώθηκαν. Δοκιμάστε ξανά.';
            }
        },
        async check() {
            const { data } = await this.request('GET');
            if ((data.version ?? 0) <= this.version) return;
            if (this.dirty) { this.conflict = data; return; }
            this.body = this.saved = data.body ?? '';
            this.version = data.version;
            this.savedAt = data.saved ?? '';
                this.status = this.savedAt ? `Αποθηκεύτηκε ${this.savedAt}` : '';
        },
        typed() {
            this.status = 'Μη αποθηκευμένες αλλαγές…';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 1000);
        },
        async save(force = false) {
            clearTimeout(this.timer);
            this.timer = null;
            if (!force && !this.dirty) {   // typed back to what is stored: nothing to save
                this.status = this.savedAt ? `Αποθηκεύτηκε ${this.savedAt}` : '';
                return;
            }
            if (this.saving) { this.timer = setTimeout(() => this.save(force), 300); return; }
            this.saving = true;
            const body = this.body;
            try {
                const { status, data } = await this.request('POST', { body, version: this.version, force });
                if (status === 409) {
                    this.conflict = data;
                    this.status = 'Οι σημειώσεις άλλαξαν σε άλλη καρτέλα: δεν αποθηκεύτηκαν.';
                } else if (status === 200) {
                    this.saved = body;
                    this.version = data.version;
                    this.conflict = null;
                    this.savedAt = data.saved;
                    this.status = `Αποθηκεύτηκε ${data.saved}`;
                } else {
                    this.status = data.message ?? 'Δεν αποθηκεύτηκε. Δοκιμάστε ξανά.';
                }
            } catch {
                this.status = 'Δεν αποθηκεύτηκε (χωρίς σύνδεση). Δοκιμάστε ξανά.';
            } finally {
                this.saving = false;
            }
        },
        takeTheirs() {
            this.body = this.saved = this.conflict.body ?? '';
            this.version = this.conflict.version;
            this.savedAt = this.conflict.saved ?? '';
            this.status = this.savedAt ? `Αποθηκεύτηκε ${this.savedAt}` : '';
            this.conflict = null;
        },
        keepMine() { this.save(true); },
        tick(e) {
            const box = e.target.closest('input[data-line]');
            if (!box) return;
            this.body = toggleTodo(this.body, Number(box.dataset.line));
            this.save();
        },
        fold(e) {
            const d = e.target.closest('details.note-section');
            if (!d) return;
            // a section drawn open (or folded) fires "toggle" too: only a real change is kept (else: drawn again, for ever)
            const title = d.dataset.title;
            if (d.open === !this.folded[title]) return;
            if (d.open) delete this.folded[title];
            else this.folded[title] = true;
            store.set('notes.folded', this.folded);
        },
        /** toolbar: wraps the selection, or starts the line(s) with a mark */
        wrap(before, after = before, placeholder = 'κείμενο') {
            const t = this.$refs.text;
            const [a, b] = [t.selectionStart, t.selectionEnd];
            const chosen = this.body.slice(a, b) || placeholder;
            this.body = this.body.slice(0, a) + before + chosen + after + this.body.slice(b);
            this.$nextTick(() => { t.focus(); t.setSelectionRange(a + before.length, a + before.length + chosen.length); });
            this.typed();
        },
        line(mark) {
            const t = this.$refs.text;
            const start = this.body.lastIndexOf('\n', t.selectionStart - 1) + 1;
            this.body = this.body.slice(0, start) + mark + this.body.slice(start);
            this.$nextTick(() => { t.focus(); t.setSelectionRange(t.selectionStart + mark.length, t.selectionStart + mark.length); });
            this.typed();
        },
    };
}

const register = () => window.Alpine.data('notepad', notepad);
if (window.Alpine) register();
document.addEventListener('alpine:init', register);
