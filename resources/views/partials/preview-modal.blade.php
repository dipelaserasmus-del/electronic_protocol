{{-- R4c: the preview of a file (one per page; filled by resources/modern/preview.js from the 👁 buttons) --}}
<div class="modal fade" id="preview-modal" tabindex="-1" aria-labelledby="preview-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down preview-dialog">
        <div class="modal-content">
            <div class="modal-header gap-2 py-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-preview-step="-1" title="Προηγούμενο αρχείο (←)" aria-label="Προηγούμενο αρχείο">‹</button>
                <span class="small text-body-secondary text-nowrap" id="preview-count"></span>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-preview-step="1" title="Επόμενο αρχείο (→)" aria-label="Επόμενο αρχείο">›</button>
                <h2 class="modal-title h6 text-truncate mb-0 flex-grow-1 ms-2" id="preview-title"></h2>
                <a class="btn btn-sm btn-outline-primary rounded-pill text-nowrap" id="preview-download" href="#" download>
                    <i class="bi bi-download me-1" aria-hidden="true"></i>Λήψη</a>
                <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
            </div>
            <div class="small px-3 py-1 bg-warning-subtle border-bottom" id="preview-note" hidden>
                Κατά προσέγγιση εμφάνιση: η μορφή (γραμματοσειρές, διάταξη) μπορεί να διαφέρει. Για το ακριβές αρχείο πατήστε <em>Λήψη</em>.
            </div>
            <div class="modal-body p-0 preview-body" id="preview-body"></div>
        </div>
    </div>
</div>
