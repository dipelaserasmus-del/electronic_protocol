{{-- R6a: page of the users (administrators): App\Livewire\UserAdmin --}}
@extends('layouts.modern')

@section('title', 'Χρήστες')

@section('content')
    <livewire:user-admin :user="$user" />
@endsection
