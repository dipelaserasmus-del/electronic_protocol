<?php

namespace App\Services\Stamp\Pdf;

/**
 * R4e: reads PDF values (numbers, names, strings, arrays, dictionaries, references, streams) and writes them back.
 * Arrays are PHP lists, dictionaries PHP arrays keyed by name (an empty one carries EMPTY_DICT).
 */
final class PdfParser
{
    public const EMPTY_DICT = "\0dict";

    private const SPACE = " \t\r\n\f\0";

    private const DELIMITERS = "()<>[]{}/%";

    /** @param null|\Closure(PdfRef): mixed $resolve for a stream's /Length given as a reference */
    public function __construct(private readonly string $data, public int $pos = 0, private readonly ?\Closure $resolve = null)
    {
    }

    public function skipSpace(): void
    {
        $len = strlen($this->data);
        while ($this->pos < $len) {
            $c = $this->data[$this->pos];
            if (str_contains(self::SPACE, $c)) {
                $this->pos++;
            } elseif ($c === '%') {
                while ($this->pos < $len && $this->data[$this->pos] !== "\n" && $this->data[$this->pos] !== "\r") {
                    $this->pos++;
                }
            } else {
                return;
            }
        }
    }

    /** "num gen obj value [stream] endobj" → [num, value] */
    public function indirectObject(): array
    {
        $this->skipSpace();
        if (! preg_match('/\G(\d+)\s+(\d+)\s+obj\b/', $this->data, $m, 0, $this->pos)) {
            throw new PdfException('no object here');
        }
        $this->pos += strlen($m[0]);
        $value = $this->value();
        $this->skipSpace();
        if (is_array($value) && substr($this->data, $this->pos, 6) === 'stream') {
            $this->pos += 6;
            if (($this->data[$this->pos] ?? '') === "\r") {
                $this->pos++;
            }
            if (($this->data[$this->pos] ?? '') === "\n") {
                $this->pos++;
            }
            $length = $value['Length'] ?? null;
            if ($length instanceof PdfRef && $this->resolve) {
                $length = ($this->resolve)($length);
            }
            if (! is_int($length) || $length < 0 || ! str_starts_with(ltrim(substr($this->data, $this->pos + $length, 32)), 'endstream')) {
                $end = strpos($this->data, 'endstream', $this->pos);   // a wrong /Length: up to "endstream"
                if ($end === false) {
                    throw new PdfException('no endstream');
                }
                $length = $end - $this->pos;
                $raw = substr($this->data, $this->pos, $length);
                $raw = preg_replace('/\r?\n$/', '', $raw);
            } else {
                $raw = substr($this->data, $this->pos, $length);
            }
            $this->pos += $length;
            $value = new PdfStream($value, $raw);
        }
        return [(int) $m[1], $value];
    }

    public function value(): mixed
    {
        $this->skipSpace();
        $c = $this->data[$this->pos] ?? '';
        if ($c === '') {
            throw new PdfException('unexpected end');
        }
        if ($c === '<' && ($this->data[$this->pos + 1] ?? '') === '<') {
            return $this->dictionary();
        }
        if ($c === '<') {
            $end = strpos($this->data, '>', $this->pos);
            if ($end === false) {
                throw new PdfException('bad hex string');
            }
            $hex = preg_replace('/[^0-9a-fA-F]/', '', substr($this->data, $this->pos + 1, $end - $this->pos - 1));
            $this->pos = $end + 1;
            return new PdfString((string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex));
        }
        if ($c === '[') {
            $this->pos++;
            $items = [];
            while (true) {
                $this->skipSpace();
                if (($this->data[$this->pos] ?? '') === ']') {
                    $this->pos++;
                    return $items;
                }
                $items[] = $this->value();
            }
        }
        if ($c === '(') {
            return $this->literal();
        }
        if ($c === '/') {
            $this->pos++;
            $start = $this->pos;
            while ($this->pos < strlen($this->data) && ! str_contains(self::SPACE . self::DELIMITERS, $this->data[$this->pos])) {
                $this->pos++;
            }
            $raw = substr($this->data, $start, $this->pos - $start);
            return new PdfName(preg_replace_callback('/#([0-9a-fA-F]{2})/', fn ($m) => chr(hexdec($m[1])), $raw));
        }
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/', $this->data, $m, 0, $this->pos)) {
            $this->pos += strlen($m[0]);
            return new PdfRef((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)/', $this->data, $m, 0, $this->pos)) {
            $this->pos += strlen($m[0]);
            return str_contains($m[0], '.') ? (float) $m[0] : (int) $m[0];
        }
        foreach (['true' => true, 'false' => false, 'null' => null] as $word => $v) {
            if (substr($this->data, $this->pos, strlen($word)) === $word) {
                $this->pos += strlen($word);
                return $v;
            }
        }
        throw new PdfException('unexpected ' . bin2hex($c) . ' at ' . $this->pos);
    }

    private function dictionary(): array
    {
        $this->pos += 2;
        $dict = [];
        while (true) {
            $this->skipSpace();
            if (substr($this->data, $this->pos, 2) === '>>') {
                $this->pos += 2;
                return $dict ?: [self::EMPTY_DICT => true];
            }
            $key = $this->value();
            if (! $key instanceof PdfName) {
                throw new PdfException('dictionary key is not a name');
            }
            $dict[$key->name] = $this->value();
        }
    }

    private function literal(): PdfString
    {
        $this->pos++;
        $depth = 1;
        $out = '';
        $len = strlen($this->data);
        while ($this->pos < $len) {
            $c = $this->data[$this->pos++];
            if ($c === '\\') {
                $n = $this->data[$this->pos++] ?? '';
                $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\'];
                if (isset($map[$n])) {
                    $out .= $map[$n];
                } elseif ($n >= '0' && $n <= '7') {
                    $oct = $n;
                    while (strlen($oct) < 3 && ($d = $this->data[$this->pos] ?? '') >= '0' && $d <= '7') {
                        $oct .= $d;
                        $this->pos++;
                    }
                    $out .= chr(octdec($oct) & 0xFF);
                } elseif ($n === "\r") {
                    if (($this->data[$this->pos] ?? '') === "\n") {
                        $this->pos++;
                    }
                } elseif ($n !== "\n") {
                    $out .= $n;
                }
                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')' && --$depth === 0) {
                return new PdfString($out);
            }
            $out .= $c;
        }
        throw new PdfException('unclosed string');
    }

    /** A value written as PDF text */
    public static function write(mixed $v): string
    {
        return match (true) {
            $v === null => 'null',
            $v === true => 'true',
            $v === false => 'false',
            is_int($v) => (string) $v,
            is_float($v) => self::number($v),
            $v instanceof PdfRef => "{$v->num} {$v->gen} R",
            $v instanceof PdfName => '/' . preg_replace_callback('/[^!-~]|[#()<>\[\]{}\/%]/', fn ($m) => sprintf('#%02X', ord($m[0])), $v->name),
            $v instanceof PdfString => '<' . bin2hex($v->bytes) . '>',
            is_array($v) && array_is_list($v) => '[' . implode(' ', array_map([self::class, 'write'], $v)) . ']',
            is_array($v) => self::dict($v),
            default => throw new PdfException('cannot write ' . get_debug_type($v)),
        };
    }

    public static function dict(array $d): string
    {
        $out = '<<';
        foreach ($d as $k => $v) {
            if ($k === self::EMPTY_DICT) {
                continue;
            }
            $out .= self::write(new PdfName((string) $k)) . ' ' . self::write($v) . ' ';
        }
        return rtrim($out) . '>>';
    }

    public static function number(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }
}
