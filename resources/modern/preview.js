// R4c: the preview modal (partials/preview-modal.blade.php). A 👁 button (<x-preview-button>) opens its file; ‹ › and
// the arrow keys go through the buttons of the same group (a protocol's files, an e-mail's attachments), wrapping round.
// PDFs: the browser's viewer; images: <img>; text, Word, spreadsheets: the page converted on the server, in a frame with
// an empty sandbox (no scripts, no forms, no navigation, its own origin); other types: a message and the download.
import { Modal } from 'bootstrap';

const read = (button) => ({
    kind: button.dataset.previewKind,
    src: button.dataset.previewSrc,
    download: button.dataset.previewDownload,
    name: button.dataset.previewName || 'αρχείο',
});

let items = [];
let index = 0;

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
    body.dataset.kind = item.kind;

    let content;
    if (!item.src) {
        content = message('Η προεπισκόπηση δεν υποστηρίζεται για αυτό το είδος αρχείου.', item.download);
    } else if (item.kind === 'image') {
        content = document.createElement('img');
        content.className = 'preview-image';
        content.alt = item.name;
        content.src = item.src;
    } else {
        content = frame(item.src, item.name, item.kind !== 'pdf');   // Chrome does not show a PDF in a sandboxed frame
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
