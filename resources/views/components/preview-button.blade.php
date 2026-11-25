{{--
    R4c: 👁 opens the file in the preview modal (partials/preview-modal, resources/modern/preview.js); ‹ › go through the
    buttons with the same "group" (a protocol's files, an e-mail's attachments).
    <x-preview-button :name="$a->name" :download="url('/download/'.$a->id)" :preview="url('/preview/'.$a->id)" group="protocol-12" />
    PDFs and images are shown from "download" (the same safe response); text, Word and spreadsheets from "preview"
    (converted on the server, sandboxed). R4j (owner, 2026-11-25): other types get a grey 👁 that is not a button (and
    is left out of ‹ ›), with a tooltip saying why.
--}}
@props(['name' => null, 'download', 'preview', 'group' => null, 'stampInFile' => false])
@php
    $kind = \App\Services\Attachments\Preview::kind($name);
    $src = match (true) {
        in_array($kind, ['pdf', 'image'], true) => $download,
        \App\Services\Attachments\Preview::converted($kind) => $preview,
        default => '',
    };
@endphp
@if ($kind === null)
<span {{ $attributes->class(['preview-none']) }} data-preview-none
      title="Δεν υπάρχει προεπισκόπηση για αυτό το είδος αρχείου· ανοίξτε το από το όνομά του." role="img"
      aria-label="Χωρίς προεπισκόπηση: «{{ $name }}»"><i class="bi bi-eye" aria-hidden="true"></i></span>
@else
<button type="button" {{ $attributes->class(['btn btn-sm btn-link p-0 text-body-secondary preview-button']) }}
        data-preview-kind="{{ $kind ?? 'none' }}" data-preview-src="{{ $src }}" data-preview-download="{{ $download }}"
        data-preview-name="{{ $name }}" @if ($group) data-preview-group="{{ $group }}" @endif @if ($stampInFile) data-preview-stamp="in-file" @endif
        title="Προεπισκόπηση" aria-label="Προεπισκόπηση του «{{ $name }}»"><i class="bi bi-eye" aria-hidden="true"></i></button>
@endif
