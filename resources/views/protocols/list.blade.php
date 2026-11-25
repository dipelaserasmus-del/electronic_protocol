{{-- R2: page of the protocol list; the list itself is the Livewire component ProtocolList. --}}
@extends('layouts.modern')

@section('title', 'Λίστα Πρωτοκόλλων')
@section('listRefresh', '1'){{-- R2m: ↻ next to «Λίστα» in the top bar --}}

@section('content')
    <livewire:protocol-list :route-filter="$filter" :route-user="$userId" />
    {{-- R2m (owner, 2026-11-25): back to the top, always at the bottom right (left of the notepad's strip) --}}
    <button type="button" class="btn btn-primary rounded-circle shadow scroll-top" data-scroll-top title="Στην αρχή της σελίδας" aria-label="Στην αρχή της σελίδας">
        <i class="bi bi-arrow-up" aria-hidden="true"></i></button>
@endsection
