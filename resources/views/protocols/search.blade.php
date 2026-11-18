{{-- R3a: page of the advanced search; the screen itself is the Livewire component SearchPage. --}}
@extends('layouts.modern')

@section('title', 'Αναζήτηση')

@section('content')
    <livewire:search-page />

    {{-- values offered while typing (as in the protocol form) and the folders for the folder picker (R1f) --}}
    @foreach (\App\Support\Suggestions::for(auth()->user()) as $field => $values)
        <datalist id="suggest-{{ $field }}">
            @foreach ($values as $value)<option value="{{ $value }}"></option>@endforeach
        </datalist>
    @endforeach
    <script type="application/json" id="suggest-folders">@json(\App\Support\Suggestions::folders(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
@endsection
