{{-- R5c: page of the inbox; the screen itself is the Livewire component EmailInbox. --}}
@extends('layouts.modern')

@section('title', 'Εισερχόμενα')

@section('content')
    {{-- R5m: deferred: the page arrives at once, the component then waits for the mail server (EmailInbox::placeholder) --}}
    <livewire:email-inbox :defer="(bool) config('imap.deferred_inbox', true)" />

    {{-- values offered while typing (most used in recent protocols) and the folders (R1f picker) --}}
    @foreach (\App\Support\Suggestions::for(auth()->user()) as $field => $values)
        <datalist id="suggest-{{ $field }}">
            @foreach ($values as $value)<option value="{{ $value }}"></option>@endforeach
        </datalist>
    @endforeach
    <script type="application/json" id="suggest-keywords">@json(\App\Support\Suggestions::keywords(auth()->user()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
    <script type="application/json" id="suggest-folders">@json(\App\Support\Suggestions::folders(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
@endsection
