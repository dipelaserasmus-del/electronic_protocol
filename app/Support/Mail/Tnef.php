<?php

namespace App\Support\Mail;

/**
 * R5b: reads a winmail.dat (TNEF, "Transport Neutral Encapsulation Format"): what Outlook / Exchange send instead of
 * a normal e-mail when "Rich Text" is on. The real message is inside: its text (plain, HTML, or compressed RTF), its
 * attachments with their long names, and, for a read receipt, the receipt's details (owner's example 2026-11-16:
 * a read receipt whose whole content is a winmail.dat).
 *
 * Format ([MS-OXTNEF]): a signature, then attributes (level, id, length, data, checksum). Attachment attributes follow
 * an attAttachRendData that starts each attachment; MAPI properties (attMAPIProps, attAttachment) carry the long file
 * names, the bodies and the report details. 8-bit strings are in the message's code page (attOemCodepage; Greek:
 * 1253). Unknown attributes and properties are skipped. decode() never throws: it returns null for anything that is
 * not readable TNEF, and the page then shows winmail.dat as a plain attachment, as before.
 */
final class Tnef
{
    private const SIGNATURE = 0x223E9F78;

    // attributes (id with its type in the high word)
    private const ATT_SUBJECT = 0x00018004;
    private const ATT_MESSAGE_CLASS = 0x00078008;
    private const ATT_BODY = 0x0002800C;
    private const ATT_ATTACH_DATA = 0x0006800F;
    private const ATT_ATTACH_TITLE = 0x00018010;
    private const ATT_ATTACH_REND_DATA = 0x00069002;
    private const ATT_MAPI_PROPS = 0x00069003;
    private const ATT_ATTACHMENT = 0x00069005;
    private const ATT_OEM_CODEPAGE = 0x00069007;

    // MAPI property ids
    private const PR_REPORT_TIME = 0x0032;
    private const PR_SUBJECT = 0x0037;
    private const PR_ORIGINAL_SUBJECT = 0x0049;
    private const PR_ORIGINAL_AUTHOR_NAME = 0x004D;
    private const PR_ORIGINAL_SUBMIT_TIME = 0x004E;
    private const PR_ORIGINAL_DISPLAY_TO = 0x0074;
    private const PR_BODY = 0x1000;
    private const PR_REPORT_TEXT = 0x1001;
    private const PR_RTF_COMPRESSED = 0x1009;
    private const PR_BODY_HTML = 0x1013;
    private const PR_DISPLAY_NAME = 0x3001;
    private const PR_ATTACH_DATA = 0x3701;
    private const PR_ATTACH_FILENAME = 0x3704;
    private const PR_ATTACH_LONG_FILENAME = 0x3707;
    private const PR_ATTACH_MIME_TAG = 0x370E;

    /** The dictionary every compressed RTF starts with ([MS-OXRTFCP]). */
    private const RTF_PREBUF = '{\rtf1\ansi\mac\deff0\deftab720{\fonttbl;}{\f0\fnil \froman \fswiss \fmodern \fscript \fdecor MS Sans SerifSymbolArialTimes New RomanCourier{\colortbl\red0\green0\blue0'."\r\n".'\par \pard\plain\f0\fs20\b\i\u\tab\tx';

    private string $codepage = 'CP1252';

    /**
     * @return array{class: string, subject: string, text: string, html: ?string, report: bool,
     *     attachments: list<array{name: string, mime: string, content: string}>}|null
     */
    public static function decode(string $data, ?\DateTimeZone $zone = null): ?array
    {
        try {
            return (new self)->read($data, $zone ?? new \DateTimeZone(date_default_timezone_get()));
        } catch (\Throwable) {
            return null;
        }
    }

    /** RTF words that stand for a character ([MS-RTF] "special characters"). */
    private const RTF_CHARACTERS = [
        'lquote' => '‘', 'rquote' => '’', 'ldblquote' => '“', 'rdblquote' => '”', 'emdash' => '—', 'endash' => '–',
        'bullet' => '•', 'emspace' => ' ', 'enspace' => ' ', 'qmspace' => ' ',
    ];

    private function read(string $d, \DateTimeZone $zone): ?array
    {
        if (strlen($d) < 6 || unpack('V', $d)[1] !== self::SIGNATURE) {
            return null;
        }
        $class = $subject = $body = '';
        $props = [];
        $attachments = [];
        $current = null;
        $pos = 6;
        $n = strlen($d);
        while ($pos + 9 <= $n) {
            $id = unpack('V', $d, $pos + 1)[1];
            $len = unpack('V', $d, $pos + 5)[1];
            if ($pos + 9 + $len + 2 > $n) {
                return null;   // cut short
            }
            $value = substr($d, $pos + 9, $len);
            $pos += 9 + $len + 2;   // level, id, length, data, checksum

            switch ($id) {
                case self::ATT_OEM_CODEPAGE:
                    $this->codepage = self::codepageName(unpack('V', $value)[1]);
                    break;
                case self::ATT_MESSAGE_CLASS:
                    $class = rtrim($value, "\0");
                    break;
                case self::ATT_SUBJECT:
                    $subject = $this->string8($value);
                    break;
                case self::ATT_BODY:
                    $body = $this->string8($value);
                    break;
                case self::ATT_MAPI_PROPS:
                    $props = $this->properties($value);
                    break;
                case self::ATT_ATTACH_REND_DATA:
                    if ($current !== null) {
                        $attachments[] = $current;
                    }
                    $current = ['title' => '', 'data' => null, 'props' => []];
                    break;
                case self::ATT_ATTACH_TITLE:
                    $current ??= ['title' => '', 'data' => null, 'props' => []];
                    $current['title'] = $this->string8($value);
                    break;
                case self::ATT_ATTACH_DATA:
                    $current ??= ['title' => '', 'data' => null, 'props' => []];
                    $current['data'] = $value;
                    break;
                case self::ATT_ATTACHMENT:
                    $current ??= ['title' => '', 'data' => null, 'props' => []];
                    $current['props'] = $this->properties($value);
                    break;
            }
        }
        if ($pos !== $n) {
            return null;   // ends inside an attribute's header: cut short (R5b review)
        }
        if ($current !== null) {
            $attachments[] = $current;
        }

        $files = [];
        foreach ($attachments as $a) {
            $content = $a['data'] ?? (is_string($a['props'][self::PR_ATTACH_DATA] ?? null) ? $a['props'][self::PR_ATTACH_DATA] : null);
            if ($content === null) {
                continue;   // an embedded Outlook item or an empty one: nothing to store
            }
            $name = $a['props'][self::PR_ATTACH_LONG_FILENAME] ?? $a['props'][self::PR_DISPLAY_NAME] ?? $a['props'][self::PR_ATTACH_FILENAME] ?? $a['title'];
            $files[] = ['name' => trim((string) $name), 'mime' => strtolower(trim((string) ($a['props'][self::PR_ATTACH_MIME_TAG] ?? ''))) ?: 'application/octet-stream',
                'content' => $content];
        }

        $text = trim((string) ($props[self::PR_BODY] ?? $body));
        if ($text === '' && is_string($props[self::PR_RTF_COMPRESSED] ?? null)) {
            $text = trim(self::rtfText(self::decompressRtf($props[self::PR_RTF_COMPRESSED])));
        }
        $report = self::isReport($class);
        if ($report && $text === '') {
            $text = $this->reportText($props, $zone);
        }
        $html = $props[self::PR_BODY_HTML] ?? null;
        $html = is_string($html) ? $this->htmlUtf8($html) : null;

        return [
            'class' => $class,
            'subject' => trim((string) ($props[self::PR_SUBJECT] ?? $subject)),
            'text' => $text,
            'html' => is_string($html) && trim($html) !== '' ? $html : null,
            'report' => $report,
            'attachments' => $files,
        ];
    }

    /**
     * Review of R5b: PR_BODY_HTML is often stored as bytes in its own charset (Greek: windows-1253): as UTF-8, by the
     * charset the page declares, else the message's code page (already UTF-8 stays as it is).
     */
    private function htmlUtf8(string $html): string
    {
        $declared = preg_match('/<meta[^>]+charset\s*=\s*["\']?([A-Za-z0-9_.:-]+)/i', $html, $m) ? strtolower($m[1]) : null;
        if (in_array($declared, ['utf-8', 'utf8'], true) || ($declared === null && mb_check_encoding($html, 'UTF-8'))) {
            return mb_scrub($html, 'UTF-8');
        }
        $from = match (true) {
            $declared === null => $this->codepage,
            (bool) preg_match('/^(windows|cp)-?(\d+)$/', $declared, $c) => 'CP'.$c[2],
            default => strtoupper($declared),
        };

        return self::utf8($html, $from);
    }

    /** Read / delivery receipts: "IPM.Microsoft Mail.Read Receipt", "REPORT.IPM.Note.IPNRN", "REPORT.IPM.Note.DR" … */
    private static function isReport(string $class): bool
    {
        return (bool) preg_match('/receipt|^report\./i', $class);
    }

    /** What a receipt says, in Greek: the report text, the original subject and recipients, when sent and read. */
    private function reportText(array $p, \DateTimeZone $zone): string
    {
        $time = fn ($v) => $v instanceof \DateTimeImmutable ? $v->setTimezone($zone)->format('d/m/Y H:i:s') : null;
        $lines = array_filter([
            'Αναφορά' => $p[self::PR_REPORT_TEXT] ?? null,
            'Θέμα' => $p[self::PR_ORIGINAL_SUBJECT] ?? null,
            'Από' => $p[self::PR_ORIGINAL_AUTHOR_NAME] ?? null,
            'Προς' => $p[self::PR_ORIGINAL_DISPLAY_TO] ?? null,
            'Στάλθηκε' => $time($p[self::PR_ORIGINAL_SUBMIT_TIME] ?? null),
            'Αναγνώστηκε' => $time($p[self::PR_REPORT_TIME] ?? null),
        ], fn ($v) => is_string($v) && trim($v) !== '');

        return implode("\n", array_map(fn ($k, $v) => "$k: ".trim($v), array_keys($lines), $lines));
    }

    /**
     * MAPI properties: id => value (strings as UTF-8, times as DateTimeImmutable UTC, binaries as bytes, numbers as int).
     * Named properties (id ≥ 0x8000) and multi-valued ones are read past and left out.
     *
     * @return array<int, mixed>
     */
    private function properties(string $d): array
    {
        $out = [];
        $count = unpack('V', $d)[1];
        $pos = 4;
        // review of R5b: every count is checked against the bytes left (each value takes at least 4), so a crafted
        // file can't make the inbox loop millions of times
        self::fits($d, $pos, $count, 4);
        for ($i = 0; $i < $count; $i++) {
            self::fits($d, $pos, 1, 4);   // every declared property must be there (R5b review)
            ['t' => $type, 'id' => $id] = unpack('vt/vid', $d, $pos);
            $pos += 4;
            if ($id >= 0x8000) {   // named property: GUID, then a number or a name
                self::fits($d, $pos, 1, 24);
                $pos += 16;
                $kind = unpack('V', $d, $pos)[1];
                $pos += 4;
                $pos += $kind === 0 ? 4 : 4 + self::pad(unpack('V', $d, $pos)[1]);
                self::fits($d, $pos, 0, 0);
            }
            $multi = ($type & 0x1000) !== 0;
            $type &= 0x0FFF;
            $values = 1;
            if ($multi || in_array($type, [0x001E, 0x001F, 0x0102, 0x000D], true)) {
                self::fits($d, $pos, 1, 4);
                $values = unpack('V', $d, $pos)[1];   // variable-length types always have a count
                $pos += 4;
                self::fits($d, $pos, $values, 4);
            }
            $value = null;
            for ($v = 0; $v < $values; $v++) {
                [$value, $pos] = $this->value($d, $pos, $type);
            }
            if (! $multi && $id < 0x8000) {
                $out[$id] = $value;
            }
        }

        return $out;
    }

    /** One value of a property of $type at $pos: [value, next position]. */
    private function value(string $d, int $pos, int $type): array
    {
        $fixed = [0x0002 => 4, 0x0003 => 4, 0x0004 => 4, 0x000A => 4, 0x000B => 4, 0x0005 => 8, 0x0006 => 8, 0x0007 => 8,
            0x0014 => 8, 0x0040 => 8, 0x0048 => 16];
        if (isset($fixed[$type])) {
            self::fits($d, $pos, 1, $fixed[$type]);
            $raw = substr($d, $pos, $fixed[$type]);
            $value = match ($type) {
                0x0002, 0x0003, 0x000A, 0x000B => unpack('V', $raw)[1],
                0x0040 => self::filetime(unpack('P', $raw)[1]),
                default => $raw,
            };

            return [$value, $pos + $fixed[$type]];
        }
        if (! in_array($type, [0x001E, 0x001F, 0x0102, 0x000D], true)) {
            throw new \UnexpectedValueException("TNEF: property type $type");   // can't know its length: stop reading
        }
        self::fits($d, $pos, 1, 4);
        $len = unpack('V', $d, $pos)[1];
        self::fits($d, $pos + 4, 1, $len);
        $raw = substr($d, $pos + 4, $len);
        $value = match ($type) {
            0x001E => $this->string8($raw),
            0x001F => rtrim(mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE'), "\0"),
            0x000D => substr($raw, 16),   // an object: its interface id, then the data
            default => $raw,
        };

        return [$value, $pos + 4 + self::pad($len)];
    }

    /** $count items of at least $size bytes each must fit in what is left from $pos, or the file is not readable */
    private static function fits(string $d, int $pos, int $count, int $size): void
    {
        if ($pos < 0 || $count < 0 || $count * max(1, $size) > strlen($d) - $pos) {
            throw new \UnexpectedValueException('TNEF: count or length beyond the data');
        }
    }

    private static function pad(int $len): int
    {
        return ($len + 3) & ~3;
    }

    /** Windows FILETIME (100 ns since 1601) → UTC time. */
    private static function filetime(int $ft): ?\DateTimeImmutable
    {
        if ($ft <= 0) {
            return null;
        }
        $seconds = intdiv($ft, 10_000_000) - 11_644_473_600;

        return (new \DateTimeImmutable('@'.$seconds))->setTimezone(new \DateTimeZone('UTC'));
    }

    /** An 8-bit string in the message's code page, as UTF-8. */
    private function string8(string $raw): string
    {
        $raw = rtrim($raw, "\0");

        return mb_check_encoding($raw, 'ASCII') ? $raw : self::utf8($raw, $this->codepage);
    }

    /** iconv's name of a Windows code page (mbstring has no Windows-1253, the Greek one) */
    /**
     * The code page of each font in the font table: its \cpgN, else its \fcharsetN, else the document's.
     * @return array<int, string>
     */
    private static function rtfFonts(string $rtf, string $doc): array
    {
        $start = strpos($rtf, '{\\fonttbl');
        if ($start === false) {
            return [];
        }
        for ($i = $start, $depth = 0, $n = strlen($rtf); $i < $n; $i++) {   // to the table's closing brace
            if ($rtf[$i] === '\\') {
                $i++;
            } elseif ($rtf[$i] === '{') {
                $depth++;
            } elseif ($rtf[$i] === '}' && --$depth === 0) {
                break;
            }
        }
        $charsets = [0 => 1252, 77 => 10000, 128 => 932, 129 => 949, 130 => 1361, 134 => 936, 136 => 950, 161 => 1253,
            162 => 1254, 163 => 1258, 177 => 1255, 178 => 1256, 186 => 1257, 204 => 1251, 222 => 874, 238 => 1250, 254 => 437];
        $fonts = [];
        preg_match_all('/\\\\f(\d+)([^;]*)/', substr($rtf, $start, $i - $start), $all, PREG_SET_ORDER);
        foreach ($all as [, $number, $rest]) {
            $fonts[(int) $number] = match (true) {
                (bool) preg_match('/\\\\cpg(\d+)/', $rest, $m) => self::codepageName((int) $m[1]),
                (bool) preg_match('/\\\\fcharset(\d+)/', $rest, $m) && isset($charsets[(int) $m[1]]) => 'CP'.$charsets[(int) $m[1]],
                default => $doc,
            };
        }

        return $fonts;
    }

    private static function codepageName(int $cp): string
    {
        return match ($cp) {
            28597 => 'ISO-8859-7', 65001 => 'UTF-8', 0 => 'CP1252',
            default => 'CP'.$cp,
        };
    }

    /** Bytes in a code page → UTF-8; what can't be converted is dropped, an unknown code page is read as Windows-1252. */
    private static function utf8(string $bytes, string $codepage): string
    {
        $out = @iconv($codepage, 'UTF-8//IGNORE', $bytes);
        if ($out === false) {
            $out = @iconv('CP1252', 'UTF-8//IGNORE', $bytes);
        }

        return $out === false ? mb_scrub($bytes, 'UTF-8') : $out;
    }

    /** [MS-OXRTFCP]: a compressed ("LZFu") or uncompressed ("MELA") RTF body → the RTF. */
    public static function decompressRtf(string $data): string
    {
        // R5b review: the sizes are checked, so a body cut short is unreadable instead of a part of the text
        if (strlen($data) < 16) {
            throw new \UnexpectedValueException('TNEF: RTF cut short');
        }
        $h = unpack('VcompSize/VrawSize/Vmagic', $data);
        if ($h['compSize'] < 12 || strlen($data) - 4 < $h['compSize']) {
            throw new \UnexpectedValueException('TNEF: RTF cut short');
        }
        $body = substr($data, 16, $h['compSize'] - 12);
        if ($h['magic'] === 0x414C454D) {   // "MELA": stored as it is
            if (strlen($body) < $h['rawSize']) {
                throw new \UnexpectedValueException('TNEF: RTF cut short');
            }

            return substr($body, 0, $h['rawSize']);
        }
        if ($h['magic'] !== 0x75465A4C) {   // not "LZFu"
            throw new \UnexpectedValueException('TNEF: unknown RTF compression');
        }
        $dict = str_pad(self::RTF_PREBUF, 4096, "\0");
        $write = strlen(self::RTF_PREBUF);
        $out = '';
        $pos = 0;
        $n = strlen($body);
        while ($pos < $n) {
            $control = ord($body[$pos++]);
            for ($bit = 0; $bit < 8 && $pos < $n; $bit++) {
                if (($control >> $bit) & 1) {   // a reference into the dictionary: 12-bit offset, 4-bit length
                    if ($pos + 2 > $n) {
                        throw new \UnexpectedValueException('TNEF: RTF cut short');
                    }
                    $word = (ord($body[$pos]) << 8) | ord($body[$pos + 1]);
                    $pos += 2;
                    $offset = $word >> 4;
                    $length = ($word & 0x0F) + 2;
                    if ($offset === $write) {   // the end marker
                        if (strlen($out) < $h['rawSize']) {
                            throw new \UnexpectedValueException('TNEF: RTF cut short');
                        }

                        return substr($out, 0, $h['rawSize']);
                    }
                    for ($i = 0; $i < $length; $i++) {
                        $c = $dict[($offset + $i) % 4096];
                        $out .= $c;
                        $dict[$write] = $c;
                        $write = ($write + 1) % 4096;
                    }
                } else {
                    $c = $body[$pos++];
                    $out .= $c;
                    $dict[$write] = $c;
                    $write = ($write + 1) % 4096;
                }
            }
        }

        throw new \UnexpectedValueException('TNEF: RTF without its end');
    }

    /**
     * The text of an RTF document: paragraphs and tabs kept, formatting dropped, hidden parts ({\*…}, font and colour
     * tables, pictures) skipped, \'hh in the font's code page (else \ansicpgN), \uN as Unicode. Enough to read and to
     * register a message (the stored e-mail keeps the original); an HTML body is used when the message has one.
     */
    public static function rtfText(string $rtf): string
    {
        $doc = preg_match('/\\\\ansicpg(\d+)/', $rtf, $m) ? self::codepageName((int) $m[1]) : 'CP1252';
        // R5b review: each font may have its own code page (Greek fonts, \fcharset161, in an \ansicpg1252 document)
        $fonts = self::rtfFonts($rtf, $doc);
        $cp = preg_match('/\\\\deff(\d+)/', $rtf, $m) ? ($fonts[(int) $m[1]] ?? $doc) : $doc;
        $skipWords = ['fonttbl', 'colortbl', 'stylesheet', 'info', 'pict', 'object', 'header', 'footer', 'listtable',
            'listoverridetable', 'rsidtbl', 'generator', 'xmlnstbl', 'themedata', 'colorschememapping', 'latentstyles', 'datastore'];
        $out = '';
        $bytes = '';   // \'hh bytes waiting to be decoded together
        $flush = function () use (&$out, &$bytes, &$cp) {
            if ($bytes !== '') {
                $out .= self::utf8($bytes, $cp);
                $bytes = '';
            }
        };
        $stack = [];
        $skip = false;
        $uc = 1;
        $skipChars = 0;
        $high = null;   // first half of a UTF-16 surrogate pair (\uN\uN), R5b review
        $n = strlen($rtf);
        for ($i = 0; $i < $n; $i++) {
            $c = $rtf[$i];
            if ($c === '{') {
                $stack[] = [$skip, $uc, $cp];
                if (substr($rtf, $i + 1, 2) === '\\*') {
                    $skip = true;
                }
                continue;
            }
            if ($c === '}') {
                $flush();   // the bytes so far are in this group's font
                [$skip, $uc, $cp] = array_pop($stack) ?? [false, 1, $cp];
                continue;
            }
            if ($c === '\\') {
                $next = $rtf[$i + 1] ?? '';
                if ($next === '\\' || $next === '{' || $next === '}') {
                    $i++;
                    if (! $skip && $skipChars-- <= 0) {
                        $flush();
                        $out .= $next;
                    }
                    continue;
                }
                if ($next === "'") {
                    $hex = substr($rtf, $i + 2, 2);
                    $i += 3;
                    if ($skipChars > 0) {
                        $skipChars--;
                    } elseif (! $skip) {
                        $bytes .= chr(hexdec($hex));
                    }
                    continue;
                }
                if (preg_match('/\G\\\\([a-zA-Z]+)(-?\d+)? ?/', $rtf, $w, 0, $i)) {
                    $i += strlen($w[0]) - 1;
                    $word = $w[1];
                    $arg = isset($w[2]) && $w[2] !== '' ? (int) $w[2] : null;
                    if ($word === 'bin') {   // N bytes of binary data, never RTF (R5b review)
                        $i += max(0, $arg ?? 0);
                    } elseif (in_array($word, $skipWords, true)) {
                        $skip = true;
                    } elseif ($word === 'uc') {
                        $uc = $arg ?? 1;
                    } elseif (! $skip) {
                        $flush();
                        if ($word === 'par' || $word === 'line' || $word === 'sect' || $word === 'row') {
                            $out .= "\n";
                        } elseif ($word === 'f' && $arg !== null) {
                            $cp = $fonts[$arg] ?? $doc;
                        } elseif ($word === 'tab' || $word === 'cell') {
                            $out .= "\t";
                        } elseif (isset(self::RTF_CHARACTERS[$word])) {
                            $out .= self::RTF_CHARACTERS[$word];   // R5b review
                        } elseif ($word === 'u' && $arg !== null) {
                            $unit = $arg < 0 ? $arg + 65536 : $arg;
                            if ($unit >= 0xD800 && $unit <= 0xDBFF) {
                                $high = $unit;
                            } elseif ($unit >= 0xDC00 && $unit <= 0xDFFF) {
                                if ($high !== null) {
                                    $out .= mb_chr(0x10000 + (($high - 0xD800) << 10) + ($unit - 0xDC00), 'UTF-8');
                                }
                                $high = null;
                            } else {
                                $out .= (string) mb_chr($unit, 'UTF-8');
                                $high = null;
                            }
                            $skipChars = $uc;
                        }
                    }
                    continue;
                }
                $i++;   // a control symbol (\~, \-, \_ …)
                if (! $skip && $next === '~') {
                    $flush();
                    $out .= ' ';
                }
                continue;
            }
            if ($c === "\r" || $c === "\n") {
                continue;
            }
            if ($skip) {
                continue;
            }
            if ($skipChars > 0) {
                $skipChars--;
                continue;
            }
            if (ord($c) >= 0x80) {   // review of R5b: a byte of the code page written as it is, not as \'hh
                $bytes .= $c;
                continue;
            }
            $flush();
            $out .= $c;
        }
        $flush();

        return preg_replace("/[ \t]+\n/", "\n", $out) ?? $out;
    }
}
