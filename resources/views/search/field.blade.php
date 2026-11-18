{{-- R3a: one text field of the search screen, bound to text.<name> (sent with the search) --}}
<div class="mb-2">
    <label for="s-{{ $name }}" class="form-label small fw-semibold mb-1">{{ $label }}</label>
    <input id="s-{{ $name }}" class="form-control @if ($error) is-invalid @endif" wire:model="text.{{ $name }}" value="{{ $value }}"
           autocomplete="off" @if ($list) list="{{ $list }}" @endif>
    @if ($error)<div class="invalid-feedback">{{ $error }}</div>@elseif ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
