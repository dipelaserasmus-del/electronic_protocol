{{--
    R1i: the header line of a section of the protocol page: a drag handle (wire:sort:handle), the title and a button that
    collapses or opens the section. Inside a <legend> the buttons stay usable when the fieldset is disabled.
    <x-form.section-head id="general" :open="$open('general')">Γενικά</x-form.section-head>
--}}
@props(['id', 'open' => true])
<span class="d-flex align-items-center gap-2 w-100">
    <span class="section-handle" wire:sort:handle title="Σύρετε για να μετακινήσετε την ενότητα" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
    <span class="flex-grow-1">{{ $slot }}</span>
    {{-- R1q (owner, 2026-11-27): not in the TAB order, so TAB goes from field to field (the mouse opens and closes it) --}}
    <button type="button" class="btn btn-sm btn-link text-reset p-0 section-toggle" tabindex="-1" wire:click="toggleSection('{{ $id }}')"
            aria-expanded="{{ $open ? 'true' : 'false' }}" title="{{ $open ? 'Σύμπτυξη' : 'Ανάπτυξη' }}" data-section-toggle="{{ $id }}">
        <i class="bi {{ $open ? 'bi-chevron-up' : 'bi-chevron-down' }}" aria-hidden="true"></i>
        <span class="visually-hidden">{{ $open ? 'Σύμπτυξη' : 'Ανάπτυξη' }}</span>
    </button>
</span>
