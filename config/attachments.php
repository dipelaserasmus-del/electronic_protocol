<?php

/*
 * R4a: what may be attached to a protocol, and how stored files are given back.
 */
return [
    // largest file, in KB (PHP, nginx and Livewire allow 64 MB: docker/php/local.ini, docker/nginx/conf.d/app.conf)
    'max_kb' => (int) env('ATTACHMENT_MAX_KB', 65536),

    // files that run code when opened (Windows, browsers, servers): refused, also when hidden behind a second
    // extension ("έγγραφο.pdf.exe") or renamed (the content is checked too). Everything else is accepted: public
    // services exchange many formats (pdf, office, images, zip, p7m/p7s signed documents, xml, eml …).
    'blocked_extensions' => [
        'exe', 'com', 'scr', 'pif', 'cpl', 'msi', 'msp', 'msc', 'dll', 'sys', 'drv', 'ocx', 'jar',
        'bat', 'cmd', 'vbs', 'vbe', 'js', 'jse', 'mjs', 'wsf', 'wsh', 'ps1', 'psm1', 'psc1', 'hta', 'reg', 'lnk', 'inf',
        'scf', 'url', 'appx', 'msix', 'application', 'gadget', 'sh', 'bash', 'php', 'phtml', 'phar', 'php3', 'php4',
        'php5', 'php7', 'php8', 'pht', 'phps', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'htaccess',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz',
    ],

    // opened in the browser tab; every other type is downloaded. Served with these types, never the stored one.
    // R4c: the largest file converted for the preview (Word, spreadsheets, text); larger ones are only downloaded
    'preview_max_kb' => (int) env('ATTACHMENT_PREVIEW_MAX_KB', 10240),

    'inline' => [
        'pdf' => 'application/pdf',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'txt' => 'text/plain; charset=utf-8',
    ],
];
