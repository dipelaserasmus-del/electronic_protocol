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
});

let items = [];
let index = 0;
let stampVersion = 0;

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
    body.dataset.kind = item.kind;

    let content;
    // R4f: a fresh copy after «Με σφραγίδα» changes (the address is the same)
    const src = item.src && item.kind === 'pdf' ? `${item.src}${item.src.includes('?') ? '&' : '?'}v=${stampVersion}` : item.src;
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
    if ($('preview-modal')?.classList.contains('show') && items[index]?.kind === 'pdf') show();
});
