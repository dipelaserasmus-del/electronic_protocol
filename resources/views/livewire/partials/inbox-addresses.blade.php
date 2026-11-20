{{--
    R5i (owner, 2026-11-20): the addresses of Προς / Κοιν.: up to three shown; a longer list (often dozens of schools)
    shows the first three and "και άλλοι N", which opens the whole list (not needed to register the e-mail).
    @include('livewire.partials.inbox-addresses', ['addresses' => $m['to'], 'kind' => 'to'])
--}}
@php $shown = 3; $more = count($addresses) - $shown; @endphp
<dd class="col-sm-10 text-break mb-1 inbox-addresses" data-addresses="{{ $kind }}" @if ($more > 0) x-data="{ all: false }" @endif>
    @if ($more > 0)
        <span>{{ implode(', ', array_slice($addresses, 0, $shown)) }}</span><span x-show="all" x-cloak>, {{ implode(', ', array_slice($addresses, $shown)) }}</span>
        <button type="button" class="btn btn-link btn-sm p-0 align-baseline ms-1 small" x-on:click="all = ! all" :aria-expanded="all.toString()" aria-expanded="false"
                data-addresses-toggle><span x-show="! all">και άλλοι {{ $more }} ▾</span><span x-show="all" x-cloak>λιγότεροι ▴</span></button>
    @else
        {{ implode(', ', $addresses) }}
    @endif
</dd>
