{{-- Z01b: sign-in on the new look (was layouts.app, Bootstrap 3). Fields and their names as before: the login keeps working. --}}
@extends('layouts.modern')

@section('title', 'Είσοδος')

@section('content')
    <x-auth-card title="Είσοδος" id="login-card">
        <form method="POST" action="{{ url('/login') }}" id="login-form" novalidate>
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">Όνομα χρήστη</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus
                       class="form-control @error('username') is-invalid @enderror">
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Κωδικός</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Να με θυμάσαι</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Είσοδος</button>
        </form>
        <x-slot:footer>
            <a href="{{ url('/password/resetForm') }}" id="login-forgot">Ξεχάσατε τον κωδικό;</a>
            @if ($allowregister ?? false)
                <span class="text-body-secondary mx-1">·</span><a href="{{ url('/register') }}" id="login-register">Εγγραφή</a>
            @endif
        </x-slot:footer>
    </x-auth-card>
@endsection
