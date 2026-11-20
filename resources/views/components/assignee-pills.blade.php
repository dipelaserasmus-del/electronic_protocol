{{--
    R2j (owner, 2026-11-20): who a protocol is assigned to, one pill per user: ✎ (bi-pencil-square) for Διεκπεραίωση,
    👁 (bi-eye) for Ενημέρωση. The same icons as the Λίστα menu's «προς Διεκπεραίωση» / «προς Ενημέρωση».
    <x-assignee-pills :assignments="$p->assignments" :names="$names" />
--}}
@props(['assignments', 'names' => [], 'inform' => true])
@php
    $kinds = ['d' => ['Διεκπεραίωση', 'pencil-square', 'assignee-handle'], 'e' => ['Ενημέρωση', 'eye', 'assignee-inform']];
@endphp
<div {{ $attributes->class(['assignee-pills d-flex flex-wrap gap-1']) }}>
    @foreach ($kinds as $type => [$label, $icon, $class])
        @continue($type === 'e' && ! $inform)
        @foreach ($assignments->where('type', $type) as $a)
            @php $name = $names[$a->user_id] ?? '—'; @endphp
            <span class="badge rounded-pill assignee-pill {{ $class }}" title="{{ $label }}: {{ $name }}" data-type="{{ $type }}">
                <i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i><span class="visually-hidden">{{ $label }}: </span>{{ $name }}</span>
        @endforeach
    @endforeach
    {{-- R2k: in the list's short rows only the first pill shows, then "+N" (all of them when the row is opened) --}}
    @if (($shown = $assignments->whereIn('type', $inform ? ['d', 'e'] : ['d'])->count()) > 1)
        <span class="badge rounded-pill text-bg-light border assignee-more" title="{{ $shown }} χρήστες">+{{ $shown - 1 }}</span>
    @endif
</div>
