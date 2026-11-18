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

const apply = { width: value => document.body.classList.toggle('layout-narrow', value === 'narrow') };

document.addEventListener('click', async e => {
    const btn = e.target.closest?.('[data-preference]');
    if (!btn) return;
    const { preference: key, value, url } = btn.dataset;
    if (!(await savePreference(url, key, value))) {
        window.appToast?.('Η επιλογή δεν αποθηκεύτηκε. Δοκιμάστε ξανά.', 'danger');
        return;
    }
    document.querySelectorAll(`[data-preference="${key}"]`).forEach(b => b.setAttribute('aria-pressed', String(b.dataset.value === value)));
    apply[key]?.(value);
});
