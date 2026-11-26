{{-- R2: page of the protocol list; the list itself is the Livewire component ProtocolList. --}}
@extends('layouts.modern')

@section('title', 'Λίστα Πρωτοκόλλων')
@section('listRefresh', '1'){{-- R2m: ↻ next to «Λίστα» in the top bar --}}

@section('content')
    <livewire:protocol-list :route-filter="$filter" :route-user="$userId" />
    @include('partials.scroll-top')
@endsection
