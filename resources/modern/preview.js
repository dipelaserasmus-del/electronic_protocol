// R4c: the preview modal (partials/preview-modal.blade.php). A 👁 button (<x-preview-button>) opens its file; ‹ › and
// the arrow keys go through the buttons of the same group (a protocol's files, an e-mail's attachments), wrapping round.
// PDFs: the browser's viewer; images: <img>; text, Word, spreadsheets: the page converted on the server, in a frame with
// an empty sandbox (no scripts, no forms, no navigation, its own origin); other types: a message and the download.
import { Modal } from 'bootstrap';
import { savePreference } from './preferences';

const read = (button) => ({
    kind: button.dataset.previewKind,
    src: button.dataset.previewSrc,
    download: button.dataset.previewDownload,
    name: button.dataset.previewName || 'αρχείο',
    stampInFile: button.dataset.previewStamp === 'in-file',   // R4f
    stampable: button.dataset.previewStampable === '1',       // R5t: «Με σφραγίδα» only where a stamp can be put
    place: button.dataset.previewPlace ?? '',                 // R4n: the stamp's place on this file
    placeUrl: button.dataset.previewPlaceUrl ?? '',
    button,
});

let items = [];
let index = 0;
let stampVersion = 0;
let saving = false;   // R4n: a stamp place being saved

const $ = (id) => document.getElementById(id);

function frame(src, title, sandboxed) {
    const f = document.createElement('iframe');
    if (sandboxed) f.setAttribute('sandbox', '');   // nothing allowed
    f.className = 'preview-frame';
    f.title = title;
    f.src = src;
    return f;
}

function message(text, download) {
    const box = document.createElement('div');
    box.className = 'preview-message text-center text-body-secondary';
    const p = document.createElement('p');
    p.textContent = text;
    const a = document.createElement('a');
    a.className = 'btn btn-primary rounded-pill';
    a.href = download;
    a.setAttribute('download', '');
    a.textContent = 'Λήψη του αρχείου';
    box.append(p, a);
    return box;
}

function show() {
    const item = items[index];
    const body = $('preview-body');
    $('preview-title').textContent = item.name;
    $('preview-title').title = item.name;
    $('preview-count').textContent = items.length > 1 ? `${index + 1} από ${items.length}` : '';
    document.querySelectorAll('[data-preview-step]').forEach((b) => { b.hidden = items.length < 2; });
    $('preview-download').href = item.download;
    $('preview-note').hidden = !['word', 'sheet'].includes(item.kind);
    $('preview-stamp-note').hidden = !item.stampInFile;
    if ($('preview-stamp')) $('preview-stamp').hidden = !item.stampable;
    showPlace(item);
    body.dataset.kind = item.kind;

    let content;
    // R4f: a fresh copy after «Με σφραγίδα» changes (the address is the same)
    // R4o: pictures take the stamp too, so they are fetched again in the same way
    const fresh = item.src && ['pdf', 'image'].includes(item.kind);
    const src = fresh ? `${item.src}${item.src.includes('?') ? '&' : '?'}v=${stampVersion}` : item.src;
    if (!item.src) {
        content = message('Η προεπισκόπηση δεν υποστηρίζεται για αυτό το είδος αρχείου.', item.download);
    } else if (item.kind === 'image') {
        content = document.createElement('img');
        content.className = 'preview-image';
        content.alt = item.name;
        content.src = src;
    } else {
        content = frame(src, item.name, item.kind !== 'pdf');   // Chrome does not show a PDF in a sandboxed frame
    }
    body.replaceChildren(content);
}

// R4n: ↖ ↑ ↗ for a file that takes the stamp, while «Με σφραγίδα» is on; the file's place pressed
function showPlace(item) {
    const group = $('preview-place');
    if (!group) return;
    const stampOn = document.querySelector('[data-stamp-toggle]')?.checked ?? false;
    group.hidden = !(item.stampable && item.placeUrl && stampOn);
    group.querySelectorAll('[data-stamp-place]').forEach((b) => {
        const on = b.dataset.stampPlace === item.place;
        b.classList.toggle('active', on);
        b.setAttribute('aria-pressed', String(on));
    });
}

function step(by) {
    if (items.length < 2) return;
    index = (index + by + items.length) % items.length;
    show();
}

document.addEventListener('click', (e) => {
    const stepper = e.target.closest?.('[data-preview-step]');
    if (stepper) { step(Number(stepper.dataset.previewStep)); return; }
    const button = e.target.closest?.('[data-preview-kind]');
    if (!button) return;
    e.preventDefault();
    const group = button.dataset.previewGroup;
    const buttons = group ? [...document.querySelectorAll('[data-preview-group]')].filter((b) => b.dataset.previewGroup === group) : [button];
    items = buttons.map(read);
    index = Math.max(0, buttons.indexOf(button));
    show();
    Modal.getOrCreateInstance($('preview-modal')).show();
});

// R4n: a place chosen: saved for the file (for every user), the PDF shown again with the stamp there
document.addEventListener('click', async (e) => {
    const choice = e.target.closest?.('[data-stamp-place]');
    const item = items[index];
    if (!choice || !item?.placeUrl) return;
    const place = choice.dataset.stampPlace;
    if (place === item.place || saving) return;
    // review of R4n: one save at a time (the buttons wait), so a quick second click never loses to the first
    saving = true;
    const group = $('preview-place');
    group.setAttribute('aria-busy', 'true');
    group.querySelectorAll('[data-stamp-place]').forEach((b) => { b.disabled = true; });
    const done = () => {
        saving = false;
        group.removeAttribute('aria-busy');
        group.querySelectorAll('[data-stamp-place]').forEach((b) => { b.disabled = false; });
    };
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let ok = false;
    try {
        const r = await fetch(item.placeUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ place }),
        });
        ok = r.ok;
    } catch { ok = false; }
    done();
    if (!ok) {
        window.appToast?.('Η θέση της σφραγίδας δεν αποθηκεύτηκε. Δοκιμάστε ξανά.', 'danger');
        return;
    }
    item.place = place;
    item.button.dataset.previewPlace = place;   // the 👁 opens it so next time too
    stampVersion++;
    show();
});

document.addEventListener('keydown', (e) => {
    if (!$('preview-modal')?.classList.contains('show') || e.altKey || e.ctrlKey || e.metaKey) return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
});

// closed: stop whatever was loading or playing
document.addEventListener('hidden.bs.modal', (e) => {
    if (e.target.id === 'preview-modal') $('preview-body').replaceChildren();
});

// R4f: «Με σφραγίδα» (<x-stamp-toggle>): saved with the account; every switch follows; an open PDF is shown again
document.addEventListener('change', async (e) => {
    const toggle = e.target.closest?.('[data-stamp-toggle]');
    if (!toggle) return;
    const on = toggle.checked;
    document.querySelectorAll('[data-stamp-toggle]').forEach((t) => { t.checked = on; });
    if (!(await savePreference(toggle.dataset.url, 'stamped', on ? 'yes' : 'no'))) {
        document.querySelectorAll('[data-stamp-toggle]').forEach((t) => { t.checked = !on; });
        window.appToast?.('Η επιλογή δεν αποθηκεύτηκε. Δοκιμάστε ξανά.', 'danger');
        return;
    }
    stampVersion++;
    if ($('preview-modal')?.classList.contains('show') && items[index]) {
        if (['pdf', 'image'].includes(items[index].kind)) show();   // R4o: pictures too
        else showPlace(items[index]);   // R4n: ↖ ↑ ↗ only while «Με σφραγίδα» is on
    }
});
