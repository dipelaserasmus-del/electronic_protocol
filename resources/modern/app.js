// N03/N04: scripts of the rebuilt pages (layouts/modern). Livewire 4 brings Alpine with it, so it is not imported here.
// The shared components work with plain event delegation, so they also work on pages without Livewire and on
// content Livewire adds later.
import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.min.css';   // R3d: icons in the top bar (<i class="bi bi-…">)
import { expandDate, shiftDate, toIso, fromIso } from './date-input';
import './tags';
import './search';
import './preferences';
import './inbox';
import './unsaved';   // R1k
import './inbox-count';   // R5f
import './preview';   // R4c
import './copy-link';   // R3h

window.bootstrap = bootstrap;

/* ---------- date input (<x-date-input>) ---------- */
const dateField = el => el.closest('[data-date-input]')?.querySelector('input[data-date-value]');

function setDate(input, value) {
    input.value = value;
    input.classList.remove('is-invalid');
    input.dispatchEvent(new Event('input', { bubbles: true }));   // Livewire wire:model / Alpine see the change
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

document.addEventListener('focusout', e => {
    const input = e.target.closest?.('input[data-date-value]');
    if (!input) return;
    const v = expandDate(input.value);
    if (v === null) input.classList.add('is-invalid');
    else if (v !== input.value) setDate(input, v);
    else input.classList.remove('is-invalid');
});

// R1c: a field showing a suggestion (data-fill-placeholder): the first click into it, while empty, puts the suggestion
// there to edit (e.g. the subject taken from an attachment's name)
document.addEventListener('focusin', e => {
    const field = e.target.closest?.('[data-fill-placeholder]');
    if (!field || field.value || !field.placeholder || field.readOnly || field.disabled) return;
    field.value = field.placeholder;
    field.dispatchEvent(new Event('input', { bubbles: true }));
});

// R1b: entering the field selects its text, so typing the day replaces the date (e.g. TAB from the number, then "9")
document.addEventListener('focusin', e => {
    const input = e.target.closest?.('input[data-date-value]');
    if (input && !input.readOnly) input.select();
});

document.addEventListener('keydown', e => {
    const input = e.target.closest?.('input[data-date-value]');
    if (!input || input.readOnly || input.disabled) return;
    // Enter accepts what was typed; ↑ / ↓ move one day, like the ◀ ▶ buttons
    if (e.key === 'Enter') { const v = expandDate(input.value); if (v) { setDate(input, v); } }
    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') { e.preventDefault(); setDate(input, shiftDate(input.value, e.key === 'ArrowUp' ? 1 : -1)); }
});

document.addEventListener('click', e => {
    const btn = e.target.closest?.('[data-date-action]');
    if (!btn) return;
    const input = dateField(btn);
    if (!input || input.readOnly || input.disabled) return;
    const action = btn.dataset.dateAction;
    if (action === 'prev') setDate(input, shiftDate(input.value, -1));
    if (action === 'next') setDate(input, shiftDate(input.value, 1));
    if (action === 'today') setDate(input, shiftDate('', 0));
    if (action === 'calendar') {
        const picker = btn.closest('[data-date-input]').querySelector('input[type=date]');
        picker.value = toIso(input.value);
        try { picker.showPicker(); } catch { picker.click(); }
    }
});

document.addEventListener('change', e => {
    if (e.target.matches?.('[data-date-input] input[type=date]')) {
        const input = dateField(e.target);
        if (e.target.value) setDate(input, fromIso(e.target.value));
        input.focus();
    }
});

/* ---------- pill action buttons (<x-pill-actions>): one row; what doesn't fit goes to the ⋯ menu ---------- */
function layoutPills(bar) {
    const pills = [...bar.querySelectorAll(':scope > .pill-actions-row > .pill')];
    const more = bar.querySelector(':scope > .pill-more');
    const menu = more.querySelector('.dropdown-menu');
    pills.forEach(p => p.classList.remove('d-none'));
    more.classList.add('d-none');
    menu.replaceChildren();
    const row = bar.querySelector(':scope > .pill-actions-row');
    if (row.scrollWidth <= row.clientWidth + 1) return;   // everything fits

    more.classList.remove('d-none');
    // hide pills from the end until the row (plus the ⋯ button) fits
    for (let i = pills.length - 1; i >= 0 && row.scrollWidth > row.clientWidth + 1; i--) {
        pills[i].classList.add('d-none');
        const item = document.createElement('li');
        const a = document.createElement('button');
        a.type = 'button';
        a.className = 'dropdown-item';
        a.textContent = pills[i].textContent.trim();
        a.addEventListener('click', () => pills[i].click());   // the hidden original does the work (link, form submit…)
        item.append(a);
        menu.prepend(item);
    }
}

function initPills(root = document) {
    root.querySelectorAll('[data-pill-actions]:not([data-pill-ready])').forEach(bar => {
        bar.dataset.pillReady = '1';
        new ResizeObserver(() => layoutPills(bar)).observe(bar);
        layoutPills(bar);
    });
}

/* ---------- toasts: window.appToast(message, type, link), Livewire $this->dispatch('toast', message: …, type: …,
   url: …, label: …). Top centre under the top bar; a click anywhere on one closes it (R1e). ---------- */
window.appToast = function (message, type = 'success', link = null) {
    const box = document.getElementById('app-toasts');
    if (!box) return;
    const colour = { success: 'success', error: 'danger', danger: 'danger', warning: 'warning', info: 'info' }[type] ?? 'secondary';
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${colour} border-0`;
    el.setAttribute('role', type === 'error' || type === 'danger' ? 'alert' : 'status');
    el.setAttribute('aria-live', 'polite');
    el.title = 'Κλικ για κλείσιμο';
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'
        + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Κλείσιμο"></button></div>';
    const body = el.querySelector('.toast-body');
    body.textContent = message;   // text only, never HTML
    // optional link (e.g. the restored file's protocol): same-site addresses only
    if (link?.url && new URL(link.url, location.href).origin === location.origin) {
        const a = document.createElement('a');
        a.href = link.url;
        a.className = 'd-block fw-semibold text-reset mt-1';
        a.textContent = link.label || link.url;
        body.append(a);
    }
    box.append(el);
    const toast = bootstrap.Toast.getOrCreateInstance(el, { delay: type === 'error' || type === 'danger' ? 10000 : 5000 });
    el.addEventListener('click', () => toast.hide());   // a link still opens
    el.addEventListener('hidden.bs.toast', () => el.remove());
    toast.show();
};
window.addEventListener('toast', e => window.appToast(e.detail?.message ?? '', e.detail?.type ?? 'success',
    e.detail?.url ? { url: e.detail.url, label: e.detail.label } : null));

/* ---------- confirm dialog: any button/link/form with data-confirm="question" ---------- */
let pendingConfirm = null;

function askConfirm(message, onYes, yesLabel) {
    const modal = document.getElementById('app-confirm');
    if (!modal) { if (window.confirm(message)) onYes(); return; }
    modal.querySelector('[data-confirm-message]').textContent = message;
    modal.querySelector('[data-confirm-yes]').textContent = yesLabel || 'Ναι';
    pendingConfirm = onYes;
    bootstrap.Modal.getOrCreateInstance(modal).show();
}

document.addEventListener('click', e => {
    const yes = e.target.closest?.('#app-confirm [data-confirm-yes]');
    if (yes) {
        const run = pendingConfirm;
        pendingConfirm = null;
        bootstrap.Modal.getInstance(document.getElementById('app-confirm'))?.hide();
        if (run) run();
        return;
    }
    const el = e.target.closest?.('a[data-confirm], button[data-confirm]');
    if (!el || el.dataset.confirmed) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    askConfirm(el.dataset.confirm, () => {
        el.dataset.confirmed = '1';
        el.click();                       // runs the normal action (link, submit, Livewire wire:click)
        delete el.dataset.confirmed;
    }, el.dataset.confirmYes);
}, true);

// capture phase: runs before the form's own submit handlers (AJAX, Livewire wire:submit), which only see the
// re-submitted event after "Ναι"
document.addEventListener('submit', e => {
    const form = e.target;
    if (!form.matches?.('form[data-confirm]') || form.dataset.confirmed) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const submitter = e.submitter ?? undefined;
    askConfirm(form.dataset.confirm, () => { form.dataset.confirmed = '1'; form.requestSubmit(submitter); delete form.dataset.confirmed; }, form.dataset.confirmYes);
}, true);

/* ---------- start ---------- */
function init() {
    initPills();
    document.querySelectorAll('#app-toasts [data-toast-message]').forEach(t => {
        window.appToast(t.dataset.toastMessage, t.dataset.toastType);
        t.remove();
    });
}
document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('morph.updated', ({ el }) => initPills(el.parentElement ?? document));
});

// exposed for the browser tests of the shared date rules
window.appDate = { expandDate, shiftDate };

/* review of R2i: a row over the whole list (ΚΕΝΟ, "no protocols") spans only the columns shown on this screen. Spanning
   the hidden ones too made the fixed-width table count them, and they took the subject's room. */
function fitFullRows(root = document) {
    root.querySelectorAll?.('table').forEach(table => {
        const cells = table.querySelectorAll('[data-full-row]');
        if (!cells.length) return;
        const shown = [...(table.tHead?.rows[0]?.cells ?? [])].filter(th => getComputedStyle(th).display !== 'none').length;
        if (shown) cells.forEach(td => { td.colSpan = shown; });
    });
}
// the content's width, not only the window's: the narrow page width (Πλάτος σελίδας) hides columns too (review of R2i)
document.addEventListener('DOMContentLoaded', () => {
    fitFullRows();
    const main = document.querySelector('main');
    if (main && window.ResizeObserver) new ResizeObserver(() => fitFullRows()).observe(main);
});
window.addEventListener('resize', () => fitFullRows());
document.addEventListener('livewire:init', () => window.Livewire.hook('morph.updated', () => fitFullRows()));

/* R2: height of the sticky top bar, for headers that stick right under it (.table-sticky) */
function measureTopbar() {
    const bar = document.querySelector('.app-topbar');
    if (bar) document.documentElement.style.setProperty('--app-topbar-height', `${bar.getBoundingClientRect().height}px`);
}
window.addEventListener('load', measureTopbar);
window.addEventListener('resize', measureTopbar);
