{{--
    N04 date input. Type "9" → 09/<this month>/<this year> (also "9/4", "9/4/25", "0904"…; rules: App\Support\DateInput),
    ◀ ▶ one day earlier/later (also ↑ ↓ keys), "Σήμερα", calendar. Posts dd/mm/yyyy under "name".
    <x-date-input name="in_date" label="Ημ/νία εισερχομένου" :value="old('in_date', $date)" />
--}}
@props(['name', 'label' => null, 'value' => '', 'id' => null, 'required' => false, 'readonly' => false, 'help' => null])
@php
    $id ??= 'date-'.\Illuminate\Support\Str::slug($name, '-');
    $error = isset($errors) ? $errors->first($name) : null;   // no error bag outside a web request
@endphp
<div {{ $attributes->whereDoesntStartWith('wire:')->class(['date-input']) }} data-date-input>
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}</label>@endif
    {{-- R1b: the buttons are not in the tab order, so TAB goes from the field before straight to the date
         (owner: "TAB after the number → day"); the keyboard has ↑ / ↓ for one day earlier/later --}}
    <div class="input-group">
        <button class="btn btn-outline-secondary" type="button" data-date-action="prev" tabindex="-1" title="Προηγούμενη ημέρα"
                aria-label="Προηγούμενη ημέρα" @disabled($readonly)>◀</button>
        <input type="text" class="form-control text-center @if ($error) is-invalid @endif" id="{{ $id }}" name="{{ $name }}"
               value="{{ $value }}" placeholder="ηη/μμ/εεεε" inputmode="numeric" autocomplete="off" maxlength="10"
               data-date-value @required($required) @readonly($readonly)
               @if ($help !== false) aria-describedby="{{ $id }}-hint" @endif {{ $attributes->whereStartsWith('wire:') }}>
        <button class="btn btn-outline-secondary" type="button" data-date-action="next" tabindex="-1" title="Επόμενη ημέρα"
                aria-label="Επόμενη ημέρα" @disabled($readonly)>▶</button>
        <button class="btn btn-outline-secondary" type="button" data-date-action="today" tabindex="-1" @disabled($readonly)>Σήμερα</button>
        <button class="btn btn-outline-secondary" type="button" data-date-action="calendar" tabindex="-1" title="Ημερολόγιο"
                aria-label="Ημερολόγιο" @disabled($readonly)>📅</button>
        <input type="date" class="visually-hidden" tabindex="-1" aria-hidden="true">
        <div class="invalid-feedback">{{ $error ?: 'Μη έγκυρη ημερομηνία (π.χ. 9, 9/4 ή 09/04/2026).' }}</div>
    </div>
    {{-- help: null = the usual hint, false = none (R5e: the inbox's compact form; the placeholder says the format) --}}
    @if ($help !== false)<div id="{{ $id }}-hint" class="form-text">{{ $help ?? 'Αρκεί η ημέρα (π.χ. 9) για τον τρέχοντα μήνα.' }}</div>@endif
</div>
