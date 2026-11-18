{{-- R7: page that chooses what to print: App\Livewire\PrintPage --}}
@extends('layouts.modern')

@section('title', 'Εκτύπωση')

@section('content')
    <livewire:print-page :mode="$mode" />
@endsection
