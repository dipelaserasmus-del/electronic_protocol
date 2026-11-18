// R5c: the inbox page (livewire/email-inbox). The list on the left scrolls to a message and marks the one being read;
// the text and HTML of the e-mails open and close together, and this browser remembers the choice.
const KEY = 'inbox.part.';

function remembered(kind) {
    try {
        return localStorage.getItem(KEY + kind) !== 'closed';   // open unless closed before
    } catch {
        return true;
    }
}

function remember(kind, open) {
    try {
        localStorage.setItem(KEY + kind, open ? 'open' : 'closed');
    } catch { /* private window: only this page */ }
}

/** Height of an HTML frame = its content (same origin; the frame runs no scripts). */
export function fit(frame) {
    try {
        const doc = frame.contentWindow?.document;
        if (!doc?.body || !frame.offsetParent) return;   // closed section: measured when opened
        // an e-mail without <!DOCTYPE> is shown in quirks mode, where the body is at least as tall as the frame:
        // shrink the frame first, then take the content's height
        frame.style.height = '0px';
        const height = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
        frame.style.height = (height + 16) + 'px';
    } catch { /* not readable: keep the default height */ }
}

function inboxPage() {
    return {
        current: null,
        go(uid) {
            const el = document.getElementById('msg-' + uid);
            if (!el) return;
            this.current = uid;
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', '#msg-' + uid);
            el.querySelector('input, textarea, select')?.focus({ preventScroll: true });
        },
        fit,
    };
}

/** One collapsible part of an e-mail: x-data="inboxPart('text')" or ('html'). */
function inboxPart(kind) {
    return {
        open: remembered(kind),
        init() {
            this.onChange = e => { if (e.detail.kind === kind) this.open = e.detail.open; };
            window.addEventListener('inbox-part', this.onChange);
        },
        destroy() { window.removeEventListener('inbox-part', this.onChange); },
        toggle() {
            const open = !this.open;
            remember(kind, open);
            window.dispatchEvent(new CustomEvent('inbox-part', { detail: { kind, open } }));
            if (open) this.$nextTick(() => this.$root.querySelectorAll('iframe').forEach(fit));
        },
        fit,
    };
}

const register = () => {
    window.Alpine.data('inboxPage', inboxPage);
    window.Alpine.data('inboxPart', inboxPart);
};
if (window.Alpine) register();
else document.addEventListener('alpine:init', register);
