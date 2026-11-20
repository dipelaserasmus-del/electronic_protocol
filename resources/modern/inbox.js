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
            // R5h: the e-mail itself takes the focus (keyboard users start there). Its first field was the folder
            // picker, whose list opened and pulled the page away from the e-mail.
            el.focus({ preventScroll: true });
            this.hold(el);
        },
        /**
         * R5h: e-mails shown as HTML load their frames late and are resized to their content; one above the e-mail
         * changes the page's height and moves it. For a moment the page stays on the e-mail, unless the user scrolls.
         */
        hold(el) {
            this.release?.();
            const top = () => parseFloat(getComputedStyle(el).scrollMarginTop) || 0;
            const keep = () => {
                const off = el.getBoundingClientRect().top - top();
                if (Math.abs(off) > 1 && !smoothScrolling) window.scrollBy(0, off);
            };
            let smoothScrolling = true;
            const settled = () => { smoothScrolling = false; keep(); };
            const observer = new ResizeObserver(keep);
            observer.observe(document.body);
            const stop = () => this.release?.();
            const timer = setTimeout(stop, 2500);
            window.addEventListener('scrollend', settled, { once: true });
            const fallback = setTimeout(settled, 900);   // browsers without scrollend
            ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach((t) => window.addEventListener(t, stop, { passive: true, once: true }));
            this.release = () => {
                observer.disconnect();
                clearTimeout(timer);
                clearTimeout(fallback);
                window.removeEventListener('scrollend', settled);
                ['wheel', 'touchstart', 'keydown', 'mousedown'].forEach((t) => window.removeEventListener(t, stop));
                this.release = null;
            };
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
