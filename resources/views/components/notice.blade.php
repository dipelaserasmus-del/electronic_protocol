{{--
    N04 inline notice next to a field: informs without blocking (replaces the old YES/NO dialogs).
    <x-notice type="warning">Υπάρχει ήδη πρωτόκολλο με αυτό το θέμα: <a href="…">123/2026</a></x-notice>
--}}
@props(['type' => 'info'])
@php $colour = ['info' => 'info', 'warning' => 'warning', 'error' => 'danger', 'success' => 'success'][$type] ?? 'info'; @endphp
<div {{ $attributes->class(["notice alert alert-$colour py-1 px-2 mb-2 small d-flex gap-2 align-items-start"]) }}
     role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <span aria-hidden="true">{{ ['info' => 'ℹ', 'warning' => '⚠', 'error' => '✖', 'success' => '✔'][$type] ?? 'ℹ' }}</span>
    <div>{{ $slot }}</div>
</div>
