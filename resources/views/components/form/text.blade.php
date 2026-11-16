{{--
    R1b: one text field of a Livewire form, bound to form.<name>. Sent with the next action, or when the field is left
    with live=true (Livewire 4: .blur alone only copies the value in the browser).
    <x-form.text name="thema" label="Θέμα" :error="$errors->first('thema')" :disabled="!$access->head" rows="2" />
    The value is also rendered by the server (wire:model only fills it in the browser). The slot goes under the field.
--}}
@props(['name', 'label', 'value' => '', 'live' => false, 'error' => null, 'disabled' => false, 'rows' => null, 'list' => null, 'type' => 'text', 'help' => null, 'max' => null])
@php $id = 'f-'.$name; @endphp
<div {{ $attributes->class(['mb-2']) }}>
    <label for="{{ $id }}" class="form-label small fw-semibold mb-1">{{ $label }}</label>
    @if ($rows)
        <textarea id="{{ $id }}" rows="{{ $rows }}" class="form-control @if ($error) is-invalid @endif"
                  wire:model{{ $live ? '.live.blur' : '.blur' }}="form.{{ $name }}" @disabled($disabled) @if ($max) maxlength="{{ $max }}" @endif>{{ $value }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" class="form-control @if ($error) is-invalid @endif" autocomplete="off"
               value="{{ $value }}" wire:model{{ $live ? '.live.blur' : '.blur' }}="form.{{ $name }}" @disabled($disabled) @if ($list) list="{{ $list }}" @endif
               @if ($max) maxlength="{{ $max }}" @endif>
    @endif
    @if ($error)<div class="invalid-feedback">{{ $error }}</div>@endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    {{ $slot }}
</div>
