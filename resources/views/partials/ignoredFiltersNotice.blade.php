{{-- B07: printouts must say when invalid criteria were ignored, otherwise a wider range looks correctly filtered.
     Labels are fixed strings; the typed values were escaped in ProtocolController::dropInvalidFilters(). --}}
@if (!empty($ignoredFilters))
    <div id="ignoredFilters" style="border:2px solid #000; padding:6px 10px; margin:8px 15px; font-weight:bold">
        ΠΡΟΣΟΧΗ: αγνοήθηκαν μη έγκυρα κριτήρια — η εκτύπωση ΔΕΝ είναι φιλτραρισμένη με αυτά:
        {!! implode(', ', $ignoredFilters) !!}
        (αριθμοί: μόνο ψηφία · ημερομηνίες: ηη/μμ/εεεε)
    </div>
@endif
