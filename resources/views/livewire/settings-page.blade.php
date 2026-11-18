{{-- R6c: the settings, drawn from App\Support\SettingsCatalog --}}
<div class="settings-page">
    <form wire:submit="save">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 sticky-actions">
            <h1 class="h4 mb-0 me-auto">Ρυθμίσεις</h1>
            <nav class="d-none d-lg-flex gap-1" aria-label="Ομάδες ρυθμίσεων">
                @foreach ($groups as $id => $g)
                    <a class="btn btn-sm btn-link text-decoration-none" href="#set-{{ $id }}"><i class="bi bi-{{ $g['icon'] }} me-1" aria-hidden="true"></i>{{ $g['title'] }}</a>
                @endforeach
            </nav>
            <button type="submit" class="btn btn-primary rounded-pill" id="settings-save">Αποθήκευση</button>
        </div>
        @if ($errors->any())
            <x-notice type="error" id="settings-errors">Δεν αποθηκεύτηκε: {{ $errors->count() === 1 ? 'ένα πεδίο θέλει' : $errors->count().' πεδία θέλουν' }} διόρθωση.</x-notice>
        @endif
        <div class="row g-3">
            @foreach ($groups as $id => $g)
                <div class="col-xl-6">
                    <section class="card shadow-sm h-100" id="set-{{ $id }}">
                        <h2 class="card-header h6 mb-0"><i class="bi bi-{{ $g['icon'] }} me-1" aria-hidden="true"></i>{{ $g['title'] }}</h2>
                        <div class="card-body">
                            @foreach ($g['settings'] as $key => $def)
                                @php $err = $errors->first('values.'.$key); $fid = 's-'.$key; @endphp
                                <div class="mb-3 setting" data-setting="{{ $key }}">
                                    @if ($def['kind'] === 'bool')
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $fid }}"
                                                   @checked($values[$key] === '1')
                                                   x-on:change="$wire.set('values.{{ $key }}', $event.target.checked ? '1' : '0', false)">
                                            <label class="form-check-label fw-semibold small" for="{{ $fid }}">{{ $def['label'] }}</label>
                                        </div>
                                    @else
                                        <label class="form-label fw-semibold small mb-1" for="{{ $fid }}">{{ $def['label'] }}</label>
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
                            @endforeach
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
        <div class="mt-3"><button type="submit" class="btn btn-primary rounded-pill">Αποθήκευση</button></div>
    </form>
</div>
