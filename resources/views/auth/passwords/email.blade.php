{{-- Z01b: ask for a new password by e-mail (the link opens auth.passwords.reset) --}}
@extends('layouts.modern')

@section('title', 'Νέος κωδικός')

@section('content')
    <x-auth-card title="Νέος κωδικός" id="forgot-card">
        <p class="small text-body-secondary">Γράψτε το e-mail του λογαριασμού σας: θα σας στείλουμε σύνδεσμο για νέο κωδικό.</p>
        <form method="POST" action="{{ url('/password/email') }}" id="forgot-form" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Αποστολή συνδέσμου</button>
        </form>
        <x-slot:footer><a href="{{ url('/login') }}">Είσοδος</a></x-slot:footer>
    </x-auth-card>
@endsection
