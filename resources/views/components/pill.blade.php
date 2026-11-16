{{-- N04 one action pill (see <x-pill-actions>): a link when "href" is given, otherwise a button. --}}
@props(['href' => null, 'variant' => 'secondary', 'type' => 'button'])
@php $classes = 'pill btn rounded-pill text-nowrap '.($variant === 'primary' || $variant === 'danger' ? 'btn-'.$variant : 'btn-outline-'.$variant); @endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
