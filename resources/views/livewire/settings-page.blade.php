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
                            @php
                                // R6d: settings that work together ('box') are drawn in one frame
                                $segments = [];
                                foreach ($g['settings'] as $key => $def) {
                                    $box = $def['box'] ?? null;
                                    if ($box !== null && $segments && end($segments)['box'] === $box) {
                                        $segments[array_key_last($segments)]['settings'][$key] = $def;
                                    } else {
                                        $segments[] = ['box' => $box, 'settings' => [$key => $def]];
                                    }
                                }
                            @endphp
                            @foreach ($segments as $segment)
                                @if ($segment['box'])
                                    @php $boxDef = $g['boxes'][$segment['box']]; @endphp
                                    <fieldset class="setting-box border rounded-3 px-3 pt-1 pb-0 mb-3" id="box-{{ $segment['box'] }}">
                                        <legend class="float-none w-auto px-2 mb-0 fs-6 fw-semibold"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ $boxDef['title'] }}</legend>
                                        <p class="small text-body-secondary mb-2">{{ $boxDef['help'] }}</p>
                                @endif
                                @foreach ($segment['settings'] as $key => $def)
                                    @include('livewire.partials.setting-field')
                                @endforeach
                                @if ($segment['box'])
                                    </fieldset>
                                @endif
                            @endforeach
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
        <div class="mt-3"><button type="submit" class="btn btn-primary rounded-pill">Αποθήκευση</button></div>
    </form>
</div>
