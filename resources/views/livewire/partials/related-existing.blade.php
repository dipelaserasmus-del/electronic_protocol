{{--
    R6l (owner, 2026-11-26): «Σχετικοί αριθμοί και στα δύο πρωτόκολλα» also for the protocols saved before the setting
    was on. Shown under the setting while it is saved on; what it changes is counted and asked first. How it works:
    docs/RELATED-NUMBERS.md (App\Services\Protocols\RelatedNumbers).
--}}
<div class="border rounded-3 p-2 mb-3 ms-4 bg-body-tertiary" id="related-existing">
    @if ($relatedPlan === null)
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="openRelated" id="related-existing-open">
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Εφαρμογή και στα υπάρχοντα πρωτόκολλα…</button>
        <div class="small text-body-secondary mt-1">Η ρύθμιση ισχύει μόνο για όσα αποθηκεύονται από εδώ και πέρα.</div>
    @else
        @php $n = $relatedPlan['protocols']; @endphp
        <div class="small fw-semibold mb-1">Σχετικοί αριθμοί και στα υπάρχοντα πρωτόκολλα</div>
        <p class="small mb-1" id="related-existing-plan">
            @if ($n === 0)
                Κανένα πρωτόκολλο δεν χρειάζεται αλλαγή: όσοι σχετικοί αριθμοί υπάρχουν είναι ήδη και στα δύο πρωτόκολλα.
            @else
                {{ $n === 1 ? '1 πρωτόκολλο παίρνει' : $n.' πρωτόκολλα παίρνουν' }} {{ $relatedPlan['numbers'] === 1 ? '1 αριθμό' : $relatedPlan['numbers'].' αριθμούς' }} στους «Σχετικούς» τους.
            @endif
            @if ($relatedPlan['left_out'] > 0)
                <span class="text-warning-emphasis">{{ $relatedPlan['left_out'] === 1 ? 'Σε 1 πρωτόκολλο' : 'Σε '.$relatedPlan['left_out'].' πρωτόκολλα' }}
                    δεν χωρούν όλοι (έως 255 χαρακτήρες)· όσοι δεν χωρούν παραλείπονται.</span>
            @endif
        </p>
        <p class="small text-body-secondary mb-2">Αν το πρωτόκολλο Α γράφει στους «Σχετικούς» το Β (π.χ. 125/2026), το Β παίρνει
            τον αριθμό του Α, όπου δεν τον έχει ήδη. Μετρούν μόνο αριθμοί της μορφής <strong>αριθμός/έτος</strong>· ό,τι είναι
            ήδη γραμμένο μένει ως έχει και οι αριθμοί μπαίνουν στο τέλος. Τα πρωτόκολλα στον κάδο δεν αλλάζουν. Κανένα e-mail.</p>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary rounded-pill" wire:click="applyRelated" id="related-existing-run"
                    wire:loading.attr="disabled" @disabled($n === 0)>Εφαρμογή σε {{ $n }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="closeRelated">Άκυρο</button>
        </div>
    @endif
</div>
