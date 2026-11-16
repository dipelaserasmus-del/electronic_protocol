{{-- N04 file-type label + size, e.g. <x-file-badge :name="$a->name" :size="$a->size" :mime="$a->mimeType" /> --}}
@props(['name' => null, 'size' => null, 'mime' => null])
@php [$kind, $colour] = \App\Support\FileBadge::kind($name, $mime); $readable = \App\Support\FileBadge::size($size); @endphp
<span {{ $attributes->class(['file-badge d-inline-flex align-items-center gap-1 text-nowrap']) }}>
    <span class="badge text-bg-{{ $colour }}"@if ($mime) title="{{ $mime }}"@endif>{{ $kind }}</span>
    @if ($readable !== '')<small class="text-body-secondary">{{ $readable }}</small>@endif
</span>
