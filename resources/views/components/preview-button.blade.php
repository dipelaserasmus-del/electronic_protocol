{{--
    R4c: 👁 opens the file in the preview modal (partials/preview-modal, resources/modern/preview.js); ‹ › go through the
    buttons with the same "group" (a protocol's files, an e-mail's attachments).
    <x-preview-button :name="$a->name" :download="url('/download/'.$a->id)" :preview="url('/preview/'.$a->id)" group="protocol-12" />
    PDFs and images are shown from "download" (the same safe response); text, Word and spreadsheets from "preview"
    (converted on the server, sandboxed). R4j (owner, 2026-11-25): other types get a grey 👁 that is not a button (and
    is left out of ‹ ›), with a tooltip saying why. R5t (owner, 2026-11-25): "stampable" (a saved protocol file that takes
    the stamp, StampedCopy::applies) shows «Με σφραγίδα» in the window; an e-mail's attachments before registering do
    not have a number yet, so the window has no such switch for them.
    R4n (owner, 2026-11-26): "stampPlace" (the attachment, when stampable) adds the stamp's place on this file and the
    address that changes it: the window then offers ↖ ↑ ↗ (top left, centre, right) next to «Με σφραγίδα».
    R4p (owner, 2026-11-27): and whether the stamp is opaque on this file (the same address): see-through / opaque.
    R4q: "stampCorner" (the stamp's own corner, read once by a page with many files, e.g. the list), so that each button
    does not read the stamp's settings again.
--}}
@props(['name' => null, 'download', 'preview', 'group' => null, 'stampInFile' => false, 'stampable' => false, 'stampPlace' => null, 'stampCorner' => null])
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
        data-preview-name="{{ $name }}" @if ($group) data-preview-group="{{ $group }}" @endif @if ($stampInFile) data-preview-stamp="in-file" @endif @if ($stampable) data-preview-stampable="1" @endif
        @if ($stampable && $stampPlace instanceof \App\Models\Attachment) data-preview-place="{{ \App\Services\Stamp\StampedCopy::place($stampPlace, $stampCorner) }}" data-preview-place-url="{{ url('/attachments/'.$stampPlace->id.'/stamp-place') }}" data-preview-opaque="{{ $stampPlace->stamp_opaque ? '1' : '0' }}" @endif
        title="Προεπισκόπηση" aria-label="Προεπισκόπηση του «{{ $name }}»"><i class="bi bi-eye" aria-hidden="true"></i></button>
@endif
