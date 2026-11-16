<?php

// Only what differs from Livewire's defaults (vendor/livewire/livewire/config/livewire.php); top-level keys given
// here replace the default ones whole.
return [
    /*
     * R1b: files attached in the protocol form are first uploaded here. Livewire's default limit is 12 MB; the
     * application allows what PHP and nginx allow (64 MB, docker/php/local.ini and docker/nginx/conf.d/app.conf),
     * as the old form did. The checks of type and size per file come in R4a.
     */
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:65536'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [],   // no temporary public preview links for uploaded documents
        'max_upload_time' => 15,
        'cleanup' => true,
    ],
];
