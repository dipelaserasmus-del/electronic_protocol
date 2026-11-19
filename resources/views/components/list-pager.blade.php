{{--
    R2e (owner, 2026-11-19): the list's pager, above and below the list.
    ‹ Προηγούμενη · page numbers (first, last, two either side of the current one, … between) · Επόμενη ›,
    then "Σελίδα [any page] από N" and the rows shown. On a phone only the buttons and the page choice.
    Uses the Livewire pagination methods (WithPagination): previousPage, nextPage, gotoPage.
--}}
@props(['paginator', 'position'])
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $name = $paginator->getPageName();
        // the lower pager brings the top of the list into view; the upper one is already there
        $scroll = $position === 'bottom' ? "\$el.closest('.protocol-list').scrollIntoView()" : '';
        $numbers = collect([1, $last])->merge(range(max(1, $current - 2), min($last, $current + 2)))->unique()->sort()->values();
    @endphp
    <nav {{ $attributes->merge(['class' => 'list-pager d-flex flex-wrap align-items-center gap-2']) }} id="pager-{{ $position }}"
         aria-label="Σελίδες της λίστας ({{ $position === 'top' ? 'πάνω' : 'κάτω' }})">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item @if ($paginator->onFirstPage()) disabled @endif">
                <button type="button" class="page-link" data-pager="previous" @disabled($paginator->onFirstPage())
                        wire:click="previousPage('{{ $name }}')" x-on:click="{{ $scroll }}" aria-label="Προηγούμενη σελίδα">
                    ‹<span class="d-none d-sm-inline"> Προηγούμενη</span></button>
            </li>
            @foreach ($numbers as $i => $number)
                @if ($i > 0 && $number > $numbers[$i - 1] + 1)
                    <li class="page-item disabled d-none d-md-block" aria-hidden="true"><span class="page-link">…</span></li>
                @endif
                <li class="page-item d-none d-md-block @if ($number === $current) active @endif" wire:key="pager-{{ $position }}-{{ $number }}">
                    @if ($number === $current)
                        <span class="page-link" aria-current="page">{{ $number }}</span>
                    @else
                        <button type="button" class="page-link" data-pager-page="{{ $number }}"
                                wire:click="gotoPage({{ $number }}, '{{ $name }}')" x-on:click="{{ $scroll }}">{{ $number }}</button>
                    @endif
                </li>
            @endforeach
            <li class="page-item @if (! $paginator->hasMorePages()) disabled @endif">
                <button type="button" class="page-link" data-pager="next" @disabled(! $paginator->hasMorePages())
                        wire:click="nextPage('{{ $name }}')" x-on:click="{{ $scroll }}" aria-label="Επόμενη σελίδα">
                    <span class="d-none d-sm-inline">Επόμενη </span>›</button>
            </li>
        </ul>
        <label class="d-flex align-items-center gap-1 small mb-0 text-nowrap">
            Σελίδα
            {{-- keyed by the page, so after a change both pagers are redrawn showing it --}}
            <select class="form-select form-select-sm w-auto" data-pager="select" wire:key="pager-{{ $position }}-select-{{ $current }}"
                    aria-label="Μετάβαση στη σελίδα"
                    x-on:change="$wire.gotoPage(Number($event.target.value), '{{ $name }}'); {{ $scroll }}">
                @for ($page = 1; $page <= $last; $page++)
                    <option value="{{ $page }}" @selected($page === $current)>{{ $page }}</option>
                @endfor
            </select>
            από {{ $last }}
        </label>
        <small class="text-body-secondary text-nowrap">Εμφάνιση {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} από {{ $paginator->total() }}</small>
    </nav>
@endif
