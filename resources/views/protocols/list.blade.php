{{-- R2: page of the protocol list; the list itself is the Livewire component ProtocolList. --}}
@extends('layouts.modern')

@section('title', 'Λίστα πρωτοκόλλων')

@section('content')
    <livewire:protocol-list :route-filter="$filter" :route-user="$userId" />
@endsection
