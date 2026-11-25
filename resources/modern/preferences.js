// R3d: the user's own screen choices (App\Support\UserPreferences), saved with the account.
// <button data-preference="width" data-value="narrow" data-url="/preferences" aria-pressed="false">: a click saves the
// choice and applies it at once; the buttons of the same choice show which one is on (aria-pressed).
const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Saves one choice; resolves to true when the server kept it. */
export async function savePreference(url, key, value) {
    try {
        const r = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token(), Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ key, value }),
        });
        return r.ok;
    } catch {
        return false;
    }
}

const apply = {
    width: value => {
        document.body.classList.toggle('layout-narrow', value === 'narrow');
        document.querySelectorAll('[data-width-steps]').forEach(showWidth);   // R3j
    },
    // R3e: the links to the home page (brand, Λίστα) lead to the new one at once
    home: (value, btn) => document.querySelectorAll('[data-home-link]').forEach(a => { a.href = btn.dataset.home; }),
};

document.addEventListener('click', async e => {
    const btn = e.target.closest?.('[data-preference]');
    if (!btn) return;
    const { preference: key, value, url } = btn.dataset;
    if (!(await savePreference(url, key, value))) {
        window.appToast?.('Η επιλογή δεν αποθηκεύτηκε. Δοκιμάστε ξανά.', 'danger');
        return;
    }
    document.querySelectorAll(`[data-preference="${key}"]`).forEach(b => b.setAttribute('aria-pressed', String(b.dataset.value === value)));
    apply[key]?.(value, btn);
});

// R3j (owner, 2026-11-25): under «Στενό, στο κέντρο», − / + make the narrow page narrower or wider (steps of
// data-step rem, data-min…data-max) and ↺ goes back to the default (about half the screen); saved with the account
// (narrowWidth; null = the default). The label says how much of the window the page takes. The menu stays open.
const rem = () => parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

/** the narrow page's width now, in rem (the user's own, or the default as it is on this screen) */
function currentWidth() {
    const own = parseFloat(document.body.style.getPropertyValue('--narrow-width'));
    if (own) return own;
    const main = document.querySelector('main');
    const max = main ? parseFloat(getComputedStyle(main).maxWidth) : NaN;
    return Number.isFinite(max) ? max / rem() : Math.max(60, window.innerWidth / 2 / rem());
}

function showWidth(group) {
    const { min, max } = group.dataset;
    const w = currentWidth();
    const px = Math.min(w * rem(), window.innerWidth);
    group.querySelector('.width-steps-value').textContent = `${Math.round(px / window.innerWidth * 100)}%`;
    group.querySelector('[data-width-step="-1"]').disabled = w <= Number(min);
    group.querySelector('[data-width-step="1"]').disabled = w >= Number(max) || w * rem() >= window.innerWidth;
    group.querySelector('[data-width-step="0"]').disabled = !document.body.style.getPropertyValue('--narrow-width');
}

let widthTimer = null;
function stepWidth(group, direction) {
    const [min, max, step] = ['min', 'max', 'step'].map(k => Number(group.dataset[k]));
    let value = null;
    if (direction) {
        const near = min + Math.round((currentWidth() - min) / step) * step;   // the default may sit between two steps
        value = Math.min(max, Math.max(min, near + direction * step));
        document.body.style.setProperty('--narrow-width', `${value}rem`);
    } else {
        document.body.style.removeProperty('--narrow-width');
    }
    showWidth(group);
    clearTimeout(widthTimer);   // several clicks in a row: one save, of the last width
    widthTimer = setTimeout(async () => {
        if (!(await savePreference(group.dataset.url, 'narrowWidth', value))) {
            window.appToast?.('Το πλάτος δεν αποθηκεύτηκε. Δοκιμάστε ξανά.', 'danger');
        }
    }, 400);
}

function bindWidthSteps() {
    document.querySelectorAll('[data-width-steps]:not([data-bound])').forEach(group => {
        group.dataset.bound = '1';
        group.addEventListener('click', e => {
            e.stopPropagation();   // Bootstrap would close the menu: keep it open to see the page change
            const btn = e.target.closest('[data-width-step]');
            if (btn && !btn.disabled) stepWidth(group, Number(btn.dataset.widthStep));
        });
        showWidth(group);
    });
}
document.addEventListener('DOMContentLoaded', bindWidthSteps);
document.addEventListener('livewire:navigated', bindWidthSteps);
document.addEventListener('shown.bs.dropdown', () => document.querySelectorAll('[data-width-steps]').forEach(showWidth));
