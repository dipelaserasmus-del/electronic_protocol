{{-- R1b: page of the protocol form (new / edit / copy); the form itself is the Livewire component ProtocolForm. --}}
@extends('layouts.modern')

@section('title', $protocol?->exists && ! $copy ? "Πρωτόκολλο {$protocol->protocolnum}/{$protocol->etos}" : 'Νέο πρωτόκολλο')

@section('content')
    <livewire:protocol-form :protocol="$protocol" :copy="$copy" :prefill-num="$prefillNum" />

    {{-- values offered while typing (most used in recent protocols) --}}
    @foreach (\App\Support\Suggestions::for(auth()->user()) as $field => $values)
        <datalist id="suggest-{{ $field }}">
            @foreach ($values as $value)<option value="{{ $value }}"></option>@endforeach
        </datalist>
    @endforeach
    {{-- R1f: searched in the browser while typing (keywords: most used first; folders: code + description) --}}
    <script type="application/json" id="suggest-keywords">@json(\App\Support\Suggestions::keywords(auth()->user()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
    <script type="application/json" id="suggest-folders">@json(\App\Support\Suggestions::folders(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
@endsection
