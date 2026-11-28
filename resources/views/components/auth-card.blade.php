{{-- Z01b: the card of the sign-in pages (login, register, forgot / reset password) on layouts.modern; $footer: links under it --}}
@props(['title', 'id'])
<div class="row justify-content-center py-4 py-md-5">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card shadow-sm auth-card" id="{{ $id }}">
            <div class="card-body p-4">
                <h1 class="h4 text-center mb-4">{{ $title }}</h1>
                @if (session('status'))
                    <div class="alert alert-success small" role="status" id="{{ $id }}-status">{{ session('status') }}</div>
                @endif
                {{ $slot }}
            </div>
        </div>
        @isset($footer)
            <div class="text-center small mt-3">{{ $footer }}</div>
        @endisset
    </div>
</div>
