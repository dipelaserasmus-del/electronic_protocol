{{--
    One setting of "Ρυθμίσεις" (R6c), from App\Support\SettingsCatalog: its field, the short line under it ('help'),
    and an ⓘ next to its name that opens the fuller explanation ('more') on hover, focus or tap (R6d).
    The ⓘ sits outside the <label>, so pressing it never flips a switch.
--}}
@php
    $err = $errors->first('values.'.$key);
    $fid = 's-'.$key;
@endphp
<div class="mb-3 setting" data-setting="{{ $key }}">
    @php
        $more = $def['more'] ?? null;
        $info = $more
            ? '<button type="button" class="btn btn-link p-0 ms-1 lh-1 align-baseline text-body-secondary setting-more" data-more="'.e($key).'"'
                .' aria-label="Τι κάνει: '.e($def['label']).'" data-bs-title="'.e($def['label']).'" data-bs-content="'.e($more).'"'
                .' x-data x-init="bootstrap.Popover.getOrCreateInstance($el, { trigger: \'hover focus\', placement: \'auto\', customClass: \'setting-popover\' })">'
                .'<i class="bi bi-info-circle" aria-hidden="true"></i></button>'
            : '';
    @endphp
    @if ($def['kind'] === 'bool')
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="{{ $fid }}"
                   @checked($values[$key] === '1')
                   x-on:change="$wire.set('values.{{ $key }}', $event.target.checked ? '1' : '0', false)">
            <label class="form-check-label fw-semibold small" for="{{ $fid }}">{{ $def['label'] }}</label>{!! $info !!}
        </div>
    @else
        <div class="mb-1"><label class="form-label fw-semibold small mb-0" for="{{ $fid }}">{{ $def['label'] }}</label>{!! $info !!}</div>
        @if ($def['kind'] === 'select')
            <select id="{{ $fid }}" class="form-select @if ($err) is-invalid @endif" wire:model="values.{{ $key }}">
                @foreach ($def['options'] as $v => $label)
                    <option value="{{ $v }}">{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($def['kind'] === 'color')
            <div class="input-group">
                <input id="{{ $fid }}" type="text" class="form-control @if ($err) is-invalid @endif" wire:model.live.blur="values.{{ $key }}" autocomplete="off" placeholder="#1f4e79">
                <input type="color" class="form-control form-control-color" aria-label="Επιλογή χρώματος"
                       value="{{ preg_match('/^#[0-9a-fA-F]{6}$/', $values[$key]) ? $values[$key] : '#0d6efd' }}"
                       x-on:change="$wire.set('values.{{ $key }}', $event.target.value)">
                @if ($err)<div class="invalid-feedback">{{ $err }}</div>@endif
            </div>
        @else
            <input id="{{ $fid }}" type="{{ $def['kind'] === 'url' ? 'url' : 'text' }}" class="form-control @if ($err) is-invalid @endif"
                   wire:model="values.{{ $key }}" autocomplete="off" @if ($def['kind'] === 'int') inputmode="decimal" @endif>
        @endif
    @endif
    @if ($err && $def['kind'] !== 'color')<div class="invalid-feedback d-block">{{ $err }}</div>@endif
    @if (! empty($def['help']))<div class="form-text">{{ $def['help'] }}</div>@endif
</div>
