{{-- R6c: the settings, drawn from App\Support\SettingsCatalog. R6f (owner, 2026-11-25): as GitHub's settings: the menu of
     sections on the left (under headings), one section on the right with its title, what it is for, and its own save --}}
<div class="settings-page row g-4">
    <nav class="col-lg-3 settings-nav" aria-label="Ενότητες ρυθμίσεων">
        <h1 class="h4 mb-3">Ρυθμίσεις</h1>
        @php $heading = false; @endphp
        @foreach ($groups as $id => $s)
            @if ($s['heading'] !== $heading)
                @if ($heading !== false)<hr class="my-2">@endif
                @if ($s['heading'])<div class="settings-nav-heading">{{ $s['heading'] }}</div>@endif
                @php $heading = $s['heading']; @endphp
            @endif
            <a href="{{ url('/settings?section='.$id) }}" wire:click.prevent="show('{{ $id }}')" data-section="{{ $id }}"
               class="settings-nav-item @if ($id === $current) active @endif" @if ($id === $current) aria-current="page" @endif>
                <i class="bi bi-{{ $s['icon'] }}" aria-hidden="true"></i><span>{{ $s['title'] }}</span></a>
        @endforeach
    </nav>

    <div class="col-lg-9 col-xl-7">
        <form wire:submit="save" class="settings-section" id="set-{{ $current }}" wire:key="section-{{ $current }}">
            <h2 class="h4 mb-1">{{ $g['title'] }}</h2>
            <p class="text-body-secondary mb-0">{{ $g['description'] }}</p>
            <hr class="mt-2 mb-3">
            @if ($errors->any())
                <x-notice type="error" id="settings-errors">Δεν αποθηκεύτηκε: {{ $errors->count() === 1 ? 'ένα πεδίο θέλει' : $errors->count().' πεδία θέλουν' }} διόρθωση.</x-notice>
            @endif
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
            <hr class="mt-1 mb-3">
            <button type="submit" class="btn btn-primary rounded-pill" id="settings-save">Αποθήκευση</button>
        </form>
    </div>
</div>
