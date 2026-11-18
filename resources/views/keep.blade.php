{{-- R6b: page of the folders (everybody sees, administrators change): App\Livewire\FolderAdmin --}}
@extends('layouts.modern')

@section('title', 'Φάκελοι')

@section('content')
    <livewire:folder-admin :keepvalue="$keepvalue" />
@endsection
