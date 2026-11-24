{{--
    R4c: 👁 opens the file in the preview modal (partials/preview-modal, resources/modern/preview.js); ‹ › go through the
    buttons with the same "group" (a protocol's files, an e-mail's attachments).
    <x-preview-button :name="$a->name" :download="url('/download/'.$a->id)" :preview="url('/preview/'.$a->id)" group="protocol-12" />
    PDFs and images are shown from "download" (the same safe response); text, Word and spreadsheets from "preview"
    (converted on the server, sandboxed). Other types: a message and the download.
--}}
@props(['name' => null, 'download', 'preview', 'group' => null])
@php
    $kind = \App\Services\Attachments\Preview::kind($name);
    $src = match (true) {
        in_array($kind, ['pdf', 'image'], true) => $download,
        \App\Services\Attachments\Preview::converted($kind) => $preview,
        default => '',
    };
@endphp
<button type="button" {{ $attributes->class(['btn btn-sm btn-link p-0 text-body-secondary preview-button']) }}
        data-preview-kind="{{ $kind ?? 'none' }}" data-preview-src="{{ $src }}" data-preview-download="{{ $download }}"
        data-preview-name="{{ $name }}" @if ($group) data-preview-group="{{ $group }}" @endif
        title="Προεπισκόπηση" aria-label="Προεπισκόπηση του «{{ $name }}»"><i class="bi bi-eye" aria-hidden="true"></i></button>
