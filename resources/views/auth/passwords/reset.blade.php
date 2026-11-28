{{-- Z01b: a new password, from the link of the e-mail (token, and the user name it was sent for) --}}
@extends('layouts.modern')

@section('title', 'Νέος κωδικός')

@section('content')
    <x-auth-card title="Νέος κωδικός" id="reset-card">
        <form method="POST" action="{{ url('/password/reset') }}" id="reset-form" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label for="username" class="form-label">Όνομα χρήστη</label>
                <input id="username" type="text" name="username" value="{{ old('username', $username ?? '') }}" autocomplete="username" required autofocus
                       class="form-control @error('username') is-invalid @enderror">
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Νέος κωδικός</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password-confirm" class="form-label">Ο κωδικός ξανά</label>
                <input id="password-confirm" type="password" name="password_confirmation" autocomplete="new-password" required
                       class="form-control @error('password_confirmation') is-invalid @enderror">
                @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Αλλαγή κωδικού</button>
        </form>
    </x-auth-card>
@endsection
