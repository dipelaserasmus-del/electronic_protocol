{{-- R5c/R5j: an e-mail's attachments (and Outlook's winmail.dat notice); ✓ = saved with the protocol while it waits --}}
@if (! empty($m['winmail']))
    {{-- R5b: Outlook's winmail.dat, opened --}}
    <x-notice type="info" class="inbox-winmail">
        @if ($m['winmail']['report'])
            <strong>Αναφορά ανάγνωσης / παράδοσης</strong> (Outlook): τα στοιχεία της φαίνονται στο κείμενο.
        @else
            Το email ήρθε ως <code>winmail.dat</code> (Outlook): εμφανίζονται το κείμενο και τα αρχεία που περιέχει.
        @endif
    </x-notice>
@endif
@if ($m['attachments'])
    <div class="inbox-attachments mb-2" role="group" aria-label="Συνημμένα">
        <div class="fw-semibold mb-1">Συνημμένα</div>
        <ul class="list-unstyled mb-0 d-flex flex-wrap gap-2">
            @foreach ($m['attachments'] as $a)
                <li class="inbox-attachment border rounded px-2 py-1 d-flex align-items-center gap-2" wire:key="a-{{ $uid }}-{{ $a['key'] }}">
                    @unless ($state)
                        <input type="checkbox" class="form-check-input mt-0" id="f-{{ $uid }}-file-{{ $a['key'] }}" wire:model="forms.{{ $uid }}.files.{{ $a['key'] }}"
                               @checked($f['files'][$a['key']] ?? false) aria-label="Αποθήκευση του {{ $a['name'] }}">
                    @endunless
                    <x-file-badge :name="$a['name']" :size="$a['size']" :mime="$a['mime']" />
                    <a href="{{ url('/viewEmailAttachment/'.$uid.'/'.$a['key']) }}" target="_blank" class="text-break">{{ $a['name'] }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif

