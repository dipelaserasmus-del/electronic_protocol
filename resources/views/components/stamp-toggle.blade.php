{{--
    R4f: «Με σφραγίδα»: the user's choice to see / download / print files with the stamp (default) or the originals.
    Saved with the account (preference "stamped", resources/modern/preview.js); every switch on the page follows.
    R4i (owner, 2026-11-25): shown to every user; the setting "putStamp" is only the choice of those who have not chosen.
--}}
<div {{ $attributes->class(['form-check form-switch mb-0 stamp-toggle']) }} title="Με σφραγίδα: τα PDF, docx, odt και οι εικόνες JPG / PNG ανοίγουν, κατεβαίνουν και τυπώνονται με τη σφραγίδα. Χωρίς: το αρχείο όπως αποθηκεύτηκε.">
    <input class="form-check-input" type="checkbox" role="switch" id="{{ $attributes->get('id', 'stamp-toggle') }}-input" data-stamp-toggle data-url="{{ url('/preferences') }}"
           @checked(\App\Services\Stamp\StampedCopy::wanted(auth()->user()))>
    <label class="form-check-label small" for="{{ $attributes->get('id', 'stamp-toggle') }}-input">Με σφραγίδα</label>
</div>
