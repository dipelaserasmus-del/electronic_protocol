<?php

namespace Tests\Support;

/**
 * R5b: builds a winmail.dat (TNEF) from invented content, for tests and the synthetic e-mails (make-emails.php
 * requires this file directly, so it uses nothing but PHP). Same structure as Outlook's: signature, attributes with
 * checksums, the message's MAPI properties, and for each attachment attAttachRendData, attAttachTitle,
 * attAttachData and attAttachment (its MAPI properties: long name, MIME type).
 */
final class TnefBuilder
{
    public const STRING8 = 0x001E;
    public const UNICODE = 0x001F;
    public const BINARY = 0x0102;
    public const LONG = 0x0003;
    public const SYSTIME = 0x0040;

    private string $attributes = '';

    public function __construct(private int $codepage = 1253)
    {
        $this->attribute(0x00089006, pack('V', 0x00010000));              // attTnefVersion
        $this->attribute(0x00069007, pack('VV', $codepage, 0));             // attOemCodepage
    }

    public function messageClass(string $class): self
    {
        return $this->attribute(0x00078008, $class."\0");
    }

    /** attSubject, in the code page */
    public function subject(string $utf8): self
    {
        return $this->attribute(0x00018004, $this->cp($utf8)."\0");
    }

    /** attBody: plain text, in the code page */
    public function body(string $utf8): self
    {
        return $this->attribute(0x0002800C, $this->cp($utf8));
    }

    /**
     * attMAPIProps of the message. @param list<array{0: int, 1: int, 2: mixed}> $props [type, id, value]; strings
     * as UTF-8 (STRING8 is written in the code page), SYSTIME as a Unix time.
     */
    public function properties(array $props): self
    {
        return $this->attribute(0x00069003, $this->props($props));
    }

    /** One attachment: 8.3 title, content, and its MAPI properties (long Unicode name, MIME type). */
    public function attachment(string $title, string $content, ?string $longName = null, ?string $mime = null): self
    {
        $this->attribute(0x00069002, pack('vVvvV', 1, 0xFFFFFFFF, 0, 0, 0));   // attAttachRendData
        $this->attribute(0x00018010, $this->cp($title)."\0");                  // attAttachTitle
        $this->attribute(0x0006800F, $content);                                // attAttachData
        $props = [];
        if ($longName !== null) {
            $props[] = [self::UNICODE, 0x3707, $longName];                      // PR_ATTACH_LONG_FILENAME
        }
        if ($mime !== null) {
            $props[] = [self::STRING8, 0x370E, $mime];                          // PR_ATTACH_MIME_TAG
        }

        return $props ? $this->attribute(0x00069005, $this->props($props)) : $this;
    }

    public function bytes(): string
    {
        return pack('Vv', 0x223E9F78, 0x0001).$this->attributes;
    }

    /**
     * PR_RTF_COMPRESSED of an RTF text ([MS-OXRTFCP]): "MELA" (stored) or "LZFu" (compressed). The compressed form
     * here starts with one dictionary reference when the text begins like the format's preset dictionary, then
     * literals, then the end marker: enough to exercise every step of the decompressor.
     */
    public static function compressedRtf(string $rtf, bool $compress = true): string
    {
        if (! $compress) {
            return pack('VVVV', strlen($rtf) + 12, strlen($rtf), 0x414C454D, 0).$rtf;
        }
        $tokens = [];
        $rest = $rtf;
        $write = 207;   // length of the preset dictionary
        if (str_starts_with($rtf, '{\rtf1\ansi')) {
            $tokens[] = ['ref', 0, 11];   // "{\rtf1\ansi" from the preset dictionary
            $rest = substr($rtf, 11);
            $write += 11;
        }
        foreach (str_split($rest) as $c) {
            $tokens[] = ['lit', $c];
            $write++;
        }
        $tokens[] = ['ref', $write % 4096, 2];   // end marker: a reference to the write position
        $out = '';
        foreach (array_chunk($tokens, 8) as $group) {
            $control = 0;
            $data = '';
            foreach ($group as $bit => $t) {
                if ($t[0] === 'ref') {
                    $control |= 1 << $bit;
                    $data .= pack('n', ($t[1] << 4) | ($t[2] - 2));
                } else {
                    $data .= $t[1];
                }
            }
            $out .= chr($control).$data;
        }

        return pack('VVVV', strlen($out) + 12, strlen($rtf), 0x75465A4C, 0).$out;
    }

    private function attribute(int $id, string $data): self
    {
        $level = in_array($id, [0x00069002, 0x00018010, 0x0006800F, 0x00069005], true) ? 2 : 1;
        $sum = array_sum(unpack('C*', $data) ?: []) % 65536;
        $this->attributes .= pack('CVV', $level, $id, strlen($data)).$data.pack('v', $sum);

        return $this;
    }

    private function props(array $props): string
    {
        $out = pack('V', count($props));
        foreach ($props as [$type, $id, $value]) {
            $out .= pack('vv', $type, $id);
            switch ($type) {
                case self::LONG:
                    $out .= pack('V', $value);
                    break;
                case self::SYSTIME:
                    $out .= pack('P', ($value + 11_644_473_600) * 10_000_000);
                    break;
                default:
                    $raw = match ($type) {
                        self::STRING8 => $this->cp($value)."\0",
                        self::UNICODE => mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')."\0\0",
                        default => $value,
                    };
                    $out .= pack('VV', 1, strlen($raw)).str_pad($raw, (strlen($raw) + 3) & ~3, "\0");
            }
        }

        return $out;
    }

    private function cp(string $utf8): string
    {
        return (string) iconv('UTF-8', 'CP'.$this->codepage, $utf8);   // mbstring has no Windows-1253
    }
}
