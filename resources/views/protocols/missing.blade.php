{{-- R3h: /protocol/{num}[/{year}] when there is no such protocol, the user may not see it, or the link is malformed:
     one page for all three, so a link never reveals that a protocol exists. --}}
@extends('layouts.modern')

@php
    $valid = preg_match('/^\d{1,9}$/', $num) && preg_match('/^\d{4}$/', $year);
    $shown = mb_strimwidth($num, 0, 20, '…') . '/' . mb_strimwidth($year, 0, 8, '…');
@endphp

@section('title', 'Δεν υπάρχει')

@section('content')
    <div class="card shadow-sm mx-auto mt-4" style="max-width: 40rem" id="protocol-missing">
        <div class="card-body p-4 text-center">
            <i class="bi bi-search display-5 text-secondary" aria-hidden="true"></i>
            <h1 class="h4 mt-3">Δεν υπάρχει</h1>
            <p class="mb-4">Δεν βρέθηκε το πρωτόκολλο <strong>{{ $shown }}</strong> ή δεν έχετε πρόσβαση σε αυτό.</p>
            @if ($trashed ?? null)
                {{-- R4h: administrators only --}}
                <x-notice type="warning" class="text-start" id="protocol-in-trash">Στον κάδο: διαγράφηκε {{ $trashed->deleted_at?->format('d/m/Y H:i') }}.
                    <a href="{{ url('/trash?tab=protocols&q='.$trashed->protocolnum) }}">Άνοιγμα στον κάδο</a></x-notice>
            @endif
            <x-pill-actions class="pill-actions-center">
                <x-pill variant="primary" :href="$valid ? url('/search?q=' . rawurlencode('αρ:' . (int) $num . '/' . $year)) : url('/find')" data-action="search">
                    <i class="bi bi-search me-1" aria-hidden="true"></i>Αναζήτηση</x-pill>
                <x-pill :href="url('/home/list')">Λίστα πρωτοκόλλων</x-pill>
            </x-pill-actions>
        </div>
    </div>
@endsection
