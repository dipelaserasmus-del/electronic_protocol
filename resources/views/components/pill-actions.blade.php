{{--
    N04 action buttons as pills, in one row; on narrow screens the ones that don't fit move to the ⋯ menu.
    <x-pill-actions>
        <x-pill type="submit" variant="primary">Αποθήκευση</x-pill>
        <x-pill :href="url('/receipt/'.$p->id)">Απόδειξη</x-pill>
        <x-pill variant="danger" data-confirm="Να διαγραφεί;">Διαγραφή</x-pill>
    </x-pill-actions>
--}}
<div {{ $attributes->class(['pill-actions d-flex align-items-center']) }} data-pill-actions>
    <div class="pill-actions-row d-flex flex-nowrap gap-2 overflow-hidden flex-grow-1 py-1">{{ $slot }}</div>
    <div class="pill-more dropdown d-none ms-2">
        <button class="btn btn-outline-secondary rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="Περισσότερες ενέργειες" title="Περισσότερες ενέργειες">⋯</button>
        <ul class="dropdown-menu dropdown-menu-end"></ul>
    </div>
</div>
