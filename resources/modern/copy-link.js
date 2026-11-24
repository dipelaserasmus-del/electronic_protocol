// R3h: a button with data-copy-link="<url>" copies the url; its [data-copy-label] says «Αντιγράφηκε» for a moment.
// navigator.clipboard needs a secure context (https or localhost); on a plain-http school server the old
// execCommand('copy') on a hidden field is used, and if that fails too the link is offered in a prompt.
const LABEL_MS = 2000;

function fallbackCopy(text) {
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch { ok = false; }
    field.remove();
    return ok;
}

async function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try { await navigator.clipboard.writeText(text); return true; } catch { /* fall through */ }
    }
    return fallbackCopy(text);
}

document.addEventListener('click', async e => {
    const button = e.target.closest?.('[data-copy-link]');
    if (!button) return;
    e.preventDefault();
    const url = button.dataset.copyLink;
    if (!(await copy(url))) {
        window.prompt('Αντιγράψτε τον σύνδεσμο:', url);
        return;
    }
    const label = button.querySelector('[data-copy-label]');
    if (!label) return;
    label.dataset.copyText ??= label.textContent;
    label.textContent = 'Αντιγράφηκε';
    button.classList.add('copied');
    clearTimeout(button._copyTimer);
    button._copyTimer = setTimeout(() => {
        label.textContent = label.dataset.copyText;
        button.classList.remove('copied');
    }, LABEL_MS);
});
