// N03/N04: scripts of the rebuilt pages (layouts/modern). Livewire 4 brings Alpine with it, so it is not imported here.
// The shared components work with plain event delegation, so they also work on pages without Livewire and on
// content Livewire adds later.
import * as bootstrap from 'bootstrap';
import { expandDate, shiftDate, toIso, fromIso } from './date-input';

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

/* ---------- toasts: window.appToast(message, type), Livewire $this->dispatch('toast', message: …, type: …) ---------- */
window.appToast = function (message, type = 'success') {
    const box = document.getElementById('app-toasts');
    if (!box) return;
    const colour = { success: 'success', error: 'danger', danger: 'danger', warning: 'warning', info: 'info' }[type] ?? 'secondary';
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${colour} border-0`;
    el.setAttribute('role', type === 'error' || type === 'danger' ? 'alert' : 'status');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'
        + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Κλείσιμο"></button></div>';
    el.querySelector('.toast-body').textContent = message;   // text only, never HTML
    box.append(el);
    el.addEventListener('hidden.bs.toast', () => el.remove());
    bootstrap.Toast.getOrCreateInstance(el, { delay: type === 'error' || type === 'danger' ? 10000 : 5000 }).show();
};
window.addEventListener('toast', e => window.appToast(e.detail?.message ?? '', e.detail?.type ?? 'success'));

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
