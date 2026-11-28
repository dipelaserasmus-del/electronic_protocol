{{-- Z01b: the first administrator's registration (open only while there is none: App\Support\Registration, S00) --}}
@extends('layouts.modern')

@section('title', 'Εγγραφή')

@section('content')
    <x-auth-card title="Εγγραφή" id="register-card">
        <form method="POST" action="{{ url('/register') }}" id="register-form" novalidate>
            @csrf
            @foreach ([
                ['name', 'Ονοματεπώνυμο', 'text', 'name'],
                ['username', 'Όνομα χρήστη', 'text', 'username'],
                ['email', 'E-mail', 'email', 'email'],
            ] as [$field, $label, $type, $complete])
                <div class="mb-3">
                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $complete }}" required
                           @if ($loop->first) autofocus @endif class="form-control @error($field) is-invalid @enderror">
                    @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="mb-3">
                <label for="password" class="form-label">Κωδικός</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password-confirm" class="form-label">Ο κωδικός ξανά</label>
                <input id="password-confirm" type="password" name="password_confirmation" autocomplete="new-password" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary w-100">Εγγραφή</button>
        </form>
        <x-slot:footer><a href="{{ url('/login') }}">Είσοδος</a></x-slot:footer>
    </x-auth-card>
@endsection
