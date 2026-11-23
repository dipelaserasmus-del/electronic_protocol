<?php

// L03: optional SMTP of the test mailbox (only the local real-data copy sets TEST_SMTP_HOST, from
// local-data/test-mailbox.env). Its mail then reaches only TEST_MAIL_ONLY_TO (App\Support\Mail\TestMailRedirect).
$pick = function (string ...$names): ?string {
    foreach ($names as $name) {
        $value = env($name);
        if ($value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }
    }

    return null;
};
$testSmtp = $pick('TEST_SMTP_HOST') !== null;

$smtp = $testSmtp ? [
    'transport' => 'smtp',
    'host' => $pick('TEST_SMTP_HOST'),
    'port' => (int) ($pick('TEST_SMTP_PORT') ?? 587),   // 465 = SSL; any other port = STARTTLS
    'username' => $pick('TEST_SMTP_USERNAME', 'TEST_IMAP_USERNAME'),
    'password' => $pick('TEST_SMTP_PASSWORD', 'TEST_IMAP_PASSWORD'),
    'timeout' => env('MAIL_TIMEOUT', 10),
    'auth_mode' => null,
] : [
    'transport' => 'smtp',
    'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
    'port' => env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'timeout' => env('MAIL_TIMEOUT', 10),   // seconds; a slow SMTP server must not hang a save (B09/B07)
    'auth_mode' => null,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses",
    |            "postmark", "log", "array"
    |
    */

    'mailers' => [
        // L03: with TEST_SMTP_HOST (local real-data copy) both mailers send through the test mailbox's SMTP
        'smtp' => $smtp,

        'intra-mail' => $smtp,

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => $testSmtp ? ($pick('TEST_SMTP_FROM', 'TEST_SMTP_USERNAME', 'TEST_IMAP_USERNAME') ?? env('MAIL_FROM_ADDRESS')) : env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],


    // L03: test mailbox SMTP on (local real-data copy) and the only addresses its mail may reach
    'test' => [
        'enabled' => $testSmtp,
        'only_to' => array_values(array_filter(array_map('trim', explode(',',
            (string) ($pick('TEST_MAIL_ONLY_TO', 'TEST_SMTP_FROM', 'TEST_SMTP_USERNAME', 'TEST_IMAP_USERNAME') ?? ''))))),
    ],

];
