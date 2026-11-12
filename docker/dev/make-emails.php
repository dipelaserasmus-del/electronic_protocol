<?php
/**
 * Generates synthetic test emails (.eml) that reproduce the tricky real-world cases
 * the app must handle: Greek legacy charsets, encoded attachment names, forwarded
 * messages, special characters, HTML-only / text-only bodies, large attachments.
 *
 * All content is invented. No real person, school or document is represented.
 *
 * Usage (inside the app container):
 *   php docker/dev/make-emails.php <fixtures-dir> [<preload-dir>]
 *
 * - <fixtures-dir>: small .eml files, committed to git (tests/fixtures/emails/synthetic)
 * - <preload-dir>:  GreenMail preload folder; receives the fixtures, any real-world
 *                   fixtures (tests/fixtures/emails/real, local-data/emails) and a
 *                   generated large-attachment email (never committed)
 */

$fixturesDir = rtrim($argv[1] ?? 'tests/fixtures/emails/synthetic', '/');
$preloadDir = isset($argv[2]) ? rtrim($argv[2], '/') : null;
$mailbox = 'protocol@school.test';
@mkdir($fixturesDir, 0777, true);

function b64h(string $s, string $charset = 'UTF-8'): string
{
    // RFC 2047: encoded words max 75 chars, folded onto continuation lines (as real clients do)
    $chunks = $charset === 'UTF-8' ? mb_str_split($s, 12, 'UTF-8') : str_split($s, 30);
    return implode("\r\n ", array_map(fn($c) => '=?' . $charset . '?B?' . base64_encode($c) . '?=', $chunks));
}

function qph(string $s, string $charset): string
{
    $q = '';
    foreach (str_split($s) as $c) {
        $o = ord($c);
        $q .= ($o > 127 || $c === '=' || $c === '?' || $c === '_') ? sprintf('=%02X', $o) : ($c === ' ' ? '_' : $c);
    }
    return '=?' . $charset . '?Q?' . $q . '?=';
}

function enc(string $utf8, string $charset): string
{
    return $charset === 'UTF-8' ? $utf8 : iconv('UTF-8', $charset . '//TRANSLIT', $utf8);
}

function minimalPdf(string $text): string
{
    $text = preg_replace('/[^A-Za-z0-9 .,-]/', '', $text);
    $stream = "BT /F1 18 Tf 50 750 Td ($text) Tj ET";
    $objs = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
        "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $i => $o) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) $pdf .= sprintf("%010d 00000 n \n", $off);
    return $pdf . "trailer << /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}

function png(): string
{
    if (!function_exists('imagecreatetruecolor')) {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
    $im = imagecreatetruecolor(120, 40);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 160));
    imagestring($im, 4, 10, 12, 'TEST SCAN', imagecolorallocate($im, 255, 255, 255));
    ob_start();
    imagepng($im);
    return ob_get_clean();
}

/**
 * @param array $m keys: from, to, subject (already encoded header value), date, parts (list), id
 *   part: ['type'=>..., 'charset'=>..., 'body'=>raw bytes, 'cte'=>'base64'|'quoted-printable'|'8bit',
 *          'filename'=>header-ready filename param string or null, 'disposition'=>...]
 */
function buildEml(array $m): string
{
    $boundary = 'b_' . md5($m['id']);
    $h = [
        'Return-Path: <' . $m['fromAddr'] . '>',
        'Message-ID: <' . $m['id'] . '@synthetic.test>',
        'Date: ' . $m['date'],
        'From: ' . $m['from'],
        'To: ' . $m['to'],
        'Subject: ' . $m['subject'],
        'MIME-Version: 1.0',
        'X-Synthetic-Case: ' . $m['case'],
    ];
    $parts = $m['parts'];
    $single = count($parts) === 1 && empty($parts[0]['filename']);
    if ($single) {
        $p = $parts[0];
        $h[] = 'Content-Type: ' . $p['type'] . (isset($p['charset']) ? '; charset="' . $p['charset'] . '"' : '');
        $h[] = 'Content-Transfer-Encoding: ' . $p['cte'];
        return implode("\r\n", $h) . "\r\n\r\n" . encodeBody($p) . "\r\n";
    }
    $h[] = 'Content-Type: multipart/' . ($m['multipart'] ?? 'mixed') . '; boundary="' . $boundary . '"';
    $out = implode("\r\n", $h) . "\r\n\r\nThis is a multi-part message in MIME format.\r\n";
    foreach ($parts as $p) {
        $out .= "--$boundary\r\n";
        if (isset($p['raw'])) {          // nested multipart already built
            $out .= $p['raw'] . "\r\n";
            continue;
        }
        $out .= 'Content-Type: ' . $p['type'] . (isset($p['charset']) ? '; charset="' . $p['charset'] . '"' : '')
            . (!empty($p['name']) ? '; ' . $p['name'] : '') . "\r\n";
        $out .= 'Content-Transfer-Encoding: ' . $p['cte'] . "\r\n";
        if (!empty($p['filename'])) {
            $out .= 'Content-Disposition: ' . ($p['disposition'] ?? 'attachment') . '; ' . $p['filename'] . "\r\n";
        }
        $out .= "\r\n" . encodeBody($p) . "\r\n";
    }
    return $out . "--$boundary--\r\n";
}

function encodeBody(array $p): string
{
    return match ($p['cte']) {
        'base64' => rtrim(chunk_split(base64_encode($p['body']), 76, "\r\n")),
        'quoted-printable' => quoted_printable_encode($p['body']),
        default => $p['body'],
    };
}

function alternative(string $id, string $text, string $html, string $charset = 'UTF-8'): array
{
    $b = 'alt_' . md5($id);
    $raw = "Content-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
        . "--$b\r\nContent-Type: text/plain; charset=\"$charset\"\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode(enc($text, $charset)) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=\"$charset\"\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode(enc($html, $charset)) . "\r\n--$b--";
    return ['raw' => $raw];
}

function attachment(string $mime, string $bytes, string $utf8Name, string $style = 'rfc2231'): array
{
    $fn = match ($style) {
        // RFC 2231 (modern clients)
        'rfc2231' => "filename*=UTF-8''" . rawurlencode($utf8Name),
        // RFC 2047 inside quotes (Outlook / older clients), Windows-1253
        'rfc2047-1253' => 'filename="' . b64h(enc($utf8Name, 'Windows-1253'), 'Windows-1253') . '"',
        // plain ASCII
        default => 'filename="' . $utf8Name . '"',
    };
    $nameParam = $style === 'rfc2047-1253' ? 'name="' . b64h(enc($utf8Name, 'Windows-1253'), 'Windows-1253') . '"' : null;
    return ['type' => $mime, 'body' => $bytes, 'cte' => 'base64', 'filename' => $fn, 'name' => $nameParam];
}

$to = 'Γραμματεία Σχολείου <' . $mailbox . '>';
$base = strtotime('2026-11-02 09:15:00 +0200');
$cases = [];

// 1. Modern UTF-8 email, PDF with Greek filename (RFC 2231)
$cases[] = [
    'case' => 'utf8-pdf-greek-filename',
    'fromAddr' => 'dnsh@dipe.example.test',
    'from' => b64h('Διεύθυνση Π.Ε. Δοκιμής') . ' <dnsh@dipe.example.test>',
    'subject' => b64h('Πρόσκληση σε επιμορφωτική ημερίδα εκπαιδευτικών'),
    'parts' => [
        alternative('c1', "Σας αποστέλλουμε συνημμένα την πρόσκληση.\n\nΜε εκτίμηση,\nΗ Διευθύντρια", '<p>Σας αποστέλλουμε <b>συνημμένα</b> την πρόσκληση.</p><p>Με εκτίμηση,<br>Η Διευθύντρια</p>'),
        attachment('application/pdf', minimalPdf('Prosklisi imerida'), 'Πρόσκληση ημερίδας 2026.pdf'),
    ],
];

// 2. Legacy ISO-8859-7: Q-encoded subject, quoted-printable body (older government systems)
$cases[] = [
    'case' => 'iso-8859-7-legacy',
    'fromAddr' => 'protocol@ypaith.example.test',
    'from' => qph(enc('Υπουργείο Δοκιμών - Τμήμα Γ', 'ISO-8859-7'), 'ISO-8859-7') . ' <protocol@ypaith.example.test>',
    'subject' => qph(enc('Εγκύκλιος για την έναρξη μαθημάτων σχολικού έτους', 'ISO-8859-7'), 'ISO-8859-7'),
    'parts' => [[
        'type' => 'text/plain', 'charset' => 'ISO-8859-7', 'cte' => 'quoted-printable',
        'body' => enc("Αγαπητοί συνάδελφοι,\n\nσας γνωρίζουμε ότι η έναρξη των μαθημάτων ορίζεται σύμφωνα με την ισχύουσα νομοθεσία.\nΤόνοι: ά έ ή ί ό ύ ώ ΐ ΰ ϊ ϋ\n", 'ISO-8859-7'),
    ]],
];

// 3. Windows-1253, HTML only, base64 body, attachment name RFC 2047 Windows-1253 (Outlook style)
$cases[] = [
    'case' => 'windows-1253-html-only-outlook-filename',
    'fromAddr' => 'school2@sch.example.test',
    'from' => b64h(enc('2ο Δημοτικό Σχολείο Δοκιμής', 'Windows-1253'), 'Windows-1253') . ' <school2@sch.example.test>',
    'subject' => b64h(enc('Διαβιβαστικό εγγράφων μαθητή', 'Windows-1253'), 'Windows-1253'),
    'parts' => [
        ['type' => 'text/html', 'charset' => 'Windows-1253', 'cte' => 'base64',
         'body' => enc('<html><body><p>Σας διαβιβάζουμε τα έγγραφα του μαθητή <i>Δοκιμαστικός Μαθητής</i>.</p><table border=1><tr><td>Τάξη</td><td>Δ΄</td></tr></table></body></html>', 'Windows-1253')],
        attachment('application/pdf', minimalPdf('Diavivastiko'), 'Διαβιβαστικό ΑΠ 123.pdf', 'rfc2047-1253'),
    ],
];

// 4. Several attachments of different types
$cases[] = [
    'case' => 'multiple-attachments',
    'fromAddr' => 'kepa@example.test',
    'from' => b64h('ΚΕΠΕΑ Δοκιμής') . ' <kepa@example.test>',
    'subject' => b64h('Αποστολή στοιχείων — πίνακες και σαρωμένο έγγραφο'),
    'parts' => [
        alternative('c4', "Συνημμένα θα βρείτε 3 αρχεία.", '<p>Συνημμένα θα βρείτε <b>3</b> αρχεία.</p>'),
        attachment('application/pdf', minimalPdf('Pinakas A'), 'Πίνακας Α.pdf'),
        attachment('image/png', png(), 'σάρωση_001.png'),
        attachment('text/csv', "Α/Α;Επώνυμο;Όνομα\n1;Δοκιμή;Πρώτος\n2;Δοκιμή;Δεύτερος\n", 'κατάσταση.csv'),
    ],
];

// 5. Special characters that break naive escaping/sanitising (B01), emoji (4-byte UTF-8)
$cases[] = [
    'case' => 'special-characters',
    'fromAddr' => 'oteam@example.test',
    'from' => b64h('Ομάδα "Δοκιμών" & Συνεργάτες') . ' <oteam@example.test>',
    'subject' => b64h('Θέμα -- με "εισαγωγικά", \'απόστροφο\', & σύμβολα: 50% ; <tag> \\ _ 📎'),
    'parts' => [[
        'type' => 'text/plain', 'charset' => 'UTF-8', 'cte' => 'quoted-printable',
        'body' => "Κείμενο με -- διπλή παύλα, ' μονό εισαγωγικό, \" διπλό, ; ερωτηματικό, % ποσοστό, _ κάτω παύλα, \\ ανάποδη κάθετο, <b>html</b> & emoji ✅ 📎 😀\n-- \nΥπογραφή μετά από --\n",
    ]],
];

// 6. Forwarded email attached as message/rfc822
$inner = buildEml([
    'id' => 'inner6', 'case' => 'inner', 'fromAddr' => 'perifereia@example.test',
    'from' => b64h('Περιφέρεια Δοκιμής') . ' <perifereia@example.test>', 'to' => 'dnsh@dipe.example.test',
    'subject' => b64h('Αρχικό μήνυμα: προγραμματισμός μεταφοράς μαθητών'),
    'date' => date(DATE_RFC2822, $base - 86400 * 3),
    'parts' => [['type' => 'text/plain', 'charset' => 'UTF-8', 'cte' => '8bit', 'body' => "Αρχικό κείμενο του προωθημένου μηνύματος.\n"]],
]);
$cases[] = [
    'case' => 'forwarded-rfc822',
    'fromAddr' => 'dnsh@dipe.example.test',
    'from' => b64h('Διεύθυνση Π.Ε. Δοκιμής') . ' <dnsh@dipe.example.test>',
    'subject' => b64h('Fwd: Αρχικό μήνυμα: προγραμματισμός μεταφοράς μαθητών'),
    'parts' => [
        ['type' => 'text/plain', 'charset' => 'UTF-8', 'cte' => '8bit', 'body' => "Σας προωθούμε το παρακάτω μήνυμα για ενέργειες.\n"],
        ['type' => 'message/rfc822', 'cte' => '8bit', 'body' => $inner, 'filename' => 'filename="' . b64h('προωθημένο.eml') . '"'],
    ],
];

// 7. Plain text only, no attachments, long subject (> 255 chars, D01)
$long = 'Ενημέρωση για ' . str_repeat('τη διαδικασία υποβολής αιτήσεων μετάθεσης και απόσπασης εκπαιδευτικών ', 5);
$cases[] = [
    'case' => 'long-subject-plain-only',
    'fromAddr' => 'pyspe@example.test',
    'from' => b64h('ΠΥΣΠΕ Δοκιμής') . ' <pyspe@example.test>',
    'subject' => b64h(mb_substr($long, 0, 300)),
    'parts' => [['type' => 'text/plain', 'charset' => 'UTF-8', 'cte' => 'quoted-printable', 'body' => "Μήνυμα μόνο με απλό κείμενο.\n" . $long . "\n"]],
];

// 8. Raw 8-bit UTF-8 in headers (non-standard but common) — frequent source of "??"
$cases[] = [
    'case' => 'raw-8bit-headers',
    'fromAddr' => 'oldsystem@example.test',
    'from' => 'Παλαιό Σύστημα <oldsystem@example.test>',
    'subject' => 'Αίτηση γονέα για βεβαίωση φοίτησης',
    'parts' => [['type' => 'text/plain', 'charset' => 'UTF-8', 'cte' => '8bit', 'body' => "Παρακαλώ για τη χορήγηση βεβαίωσης φοίτησης.\n"]],
];

$written = [];
foreach ($cases as $i => $c) {
    $c['id'] = 'syn' . ($i + 1);
    $c['to'] = $to;
    $c['date'] = date(DATE_RFC2822, $base + $i * 3600);
    $file = sprintf('%s/%02d-%s.eml', $fixturesDir, $i + 1, $c['case']);
    file_put_contents($file, buildEml($c));
    $written[] = $file;
}
echo 'Fixtures written: ' . count($written) . " -> $fixturesDir\n";

if ($preloadDir) {
    $inbox = "$preloadDir/$mailbox/INBOX";
    @mkdir($inbox, 0777, true);
    array_map('unlink', glob("$inbox/*.eml") ?: []);
    $sources = array_merge(
        glob("$fixturesDir/*.eml") ?: [],
        glob('tests/fixtures/emails/real/*.eml') ?: [],
        glob('local-data/emails/*.eml') ?: []      // owner's private emails, never committed
    );
    foreach ($sources as $src) copy($src, "$inbox/" . basename($src));
    // Large attachment (12 MB), generated only for the mock server
    $big = buildEml([
        'id' => 'big', 'case' => 'large-attachment', 'fromAddr' => 'scanner@example.test',
        'from' => b64h('Σαρωτής Γραφείου') . ' <scanner@example.test>', 'to' => $to,
        'subject' => b64h('Μεγάλο σαρωμένο αρχείο (12MB)'), 'date' => date(DATE_RFC2822, $base + 86400),
        'parts' => [
            ['type' => 'text/plain', 'charset' => 'UTF-8', 'cte' => '8bit', 'body' => "Συνημμένο μεγάλο αρχείο.\n"],
            attachment('application/pdf', minimalPdf('Big scan') . random_bytes(12 * 1024 * 1024), 'μεγάλη_σάρωση.pdf'),
        ],
    ]);
    file_put_contents("$inbox/99-large-attachment.eml", $big);
    echo 'Mock mailbox preloaded: ' . (count($sources) + 1) . " emails -> $inbox\n";
}
