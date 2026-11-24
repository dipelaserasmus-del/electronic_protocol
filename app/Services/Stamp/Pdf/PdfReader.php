<?php

namespace App\Services\Stamp\Pdf;

/**
 * R4e: just enough of a PDF reader to find the pages of a document and their properties: the cross-reference data
 * (classic tables and cross-reference streams, following /Prev), objects (also inside object streams), and the
 * page tree with its inherited attributes. Anything it does not understand throws PdfException; the caller then
 * leaves the file as it is.
 */
final class PdfReader
{
    /** @var array<int, array{0: int, 1: int, 2: int}> object => [type 1|2, offset|stream, generation|index] */
    private array $xref = [];

    /** the newest trailer */
    public array $trailer = [];

    /** the last cross-reference section is a stream (an update then writes one too) */
    public bool $xrefStream = false;

    public int $startxref = 0;

    /** @var array<int, mixed> */
    private array $cache = [];

    /** @var array<int, array<int, int>> object stream => [object => offset] */
    private array $objectStreams = [];

    public function __construct(public readonly string $data)
    {
        if (! str_starts_with(ltrim(substr($data, 0, 1024)), '%PDF-') && ! str_contains(substr($data, 0, 1024), '%PDF-')) {
            throw new PdfException('not a PDF');
        }
        $at = strrpos($data, 'startxref');
        if ($at === false || ! preg_match('/startxref\s+(\d+)/', substr($data, $at, 64), $m)) {
            throw new PdfException('no startxref');
        }
        $this->startxref = (int) $m[1];
        $seen = [];
        $offset = $this->startxref;
        $first = true;
        while ($offset !== null) {
            if (isset($seen[$offset]) || count($seen) > 100) {
                throw new PdfException('xref loop');
            }
            $seen[$offset] = true;
            [$trailer, $isStream] = $this->readXref($offset);
            if ($first) {
                $this->trailer = $trailer;
                $this->xrefStream = $isStream;
                $first = false;
            }
            if (isset($trailer['XRefStm']) && is_int($trailer['XRefStm'])) {   // hybrid file
                $this->readXref($trailer['XRefStm']);
            }
            $offset = isset($trailer['Prev']) && is_int($trailer['Prev']) ? $trailer['Prev'] : null;
        }
        if (! isset($this->trailer['Root']) || ! $this->trailer['Root'] instanceof PdfRef) {
            throw new PdfException('no /Root');
        }
    }

    public function encrypted(): bool
    {
        return isset($this->trailer['Encrypt']);
    }

    public function size(): int
    {
        return max((int) ($this->trailer['Size'] ?? 0), $this->xref ? max(array_keys($this->xref)) + 1 : 0);
    }

    /** generation of an object (0 for objects in object streams) */
    public function generation(int $num): int
    {
        $e = $this->xref[$num] ?? null;
        return $e && $e[0] === 1 ? $e[2] : 0;
    }

    /** a value with references followed (once) */
    public function resolve(mixed $value): mixed
    {
        return $value instanceof PdfRef ? $this->object($value->num) : $value;
    }

    /** The object $num (a stream: PdfStream) */
    public function object(int $num): mixed
    {
        if (array_key_exists($num, $this->cache)) {
            return $this->cache[$num];
        }
        $e = $this->xref[$num] ?? null;
        if ($e === null) {
            return $this->cache[$num] = null;
        }
        if ($e[0] === 1) {
            $value = $this->objectAt($e[1], $num);
        } else {
            $value = $this->fromObjectStream($e[1], $num);
        }
        return $this->cache[$num] = $value;
    }

    /**
     * The pages in order: [object number, page dictionary, inherited Resources / MediaBox / CropBox / Rotate]
     *
     * @return list<array{0: int, 1: array, 2: array}>
     */
    public function pages(): array
    {
        $root = $this->resolve($this->trailer['Root']);
        if (! is_array($root) || ! isset($root['Pages']) || ! $root['Pages'] instanceof PdfRef) {
            throw new PdfException('no page tree');
        }
        $pages = [];
        $this->walk($root['Pages']->num, [], $pages, []);
        if (! $pages) {
            throw new PdfException('no pages');
        }
        return $pages;
    }

    private function walk(int $num, array $inherited, array &$pages, array $path): void
    {
        if (isset($path[$num]) || count($path) > 64 || count($pages) > 100000) {
            throw new PdfException('page tree loop');
        }
        $path[$num] = true;
        $node = $this->object($num);
        if (! is_array($node)) {
            throw new PdfException('bad page tree node');
        }
        foreach (['Resources', 'MediaBox', 'CropBox', 'Rotate'] as $key) {
            if (array_key_exists($key, $node)) {
                $inherited[$key] = $node[$key];
            }
        }
        $type = $node['Type'] ?? null;
        if ($type instanceof PdfName && $type->name === 'Page' || ! isset($node['Kids'])) {
            $pages[] = [$num, $node, $inherited];
            return;
        }
        foreach ((array) $this->resolve($node['Kids']) as $kid) {
            if ($kid instanceof PdfRef) {
                $this->walk($kid->num, $inherited, $pages, $path);
            }
        }
    }

    /** @return array{0: array, 1: bool} trailer, is a stream */
    private function readXref(int $offset): array
    {
        $p = new PdfParser($this->data, $offset);
        $p->skipSpace();
        if (substr($this->data, $p->pos, 4) === 'xref') {
            $p->pos += 4;
            while (true) {
                $p->skipSpace();
                if (substr($this->data, $p->pos, 7) === 'trailer') {
                    $p->pos += 7;
                    $trailer = $p->value();
                    if (! is_array($trailer)) {
                        throw new PdfException('bad trailer');
                    }
                    return [$trailer, false];
                }
                if (! preg_match('/\G(\d+)\s+(\d+)/', $this->data, $m, 0, $p->pos)) {
                    throw new PdfException('bad xref');
                }
                $p->pos += strlen($m[0]);
                [$start, $count] = [(int) $m[1], (int) $m[2]];
                for ($i = 0; $i < $count; $i++) {
                    $p->skipSpace();
                    if (! preg_match('/\G(\d{10}) (\d{5}) ([nf])/', $this->data, $e, 0, $p->pos)) {
                        throw new PdfException('bad xref entry');
                    }
                    $p->pos += 18;
                    if ($e[3] === 'n' && ! isset($this->xref[$start + $i])) {
                        $this->xref[$start + $i] = [1, (int) $e[1], (int) $e[2]];
                    } elseif ($e[3] === 'f' && ! isset($this->xref[$start + $i])) {
                        $this->xref[$start + $i] = [0, 0, 0];
                    }
                }
            }
        }
        // a cross-reference stream
        [$num, $value] = $p->indirectObject();
        if (! $value instanceof PdfStream || ($value->dict['Type'] ?? null) != new PdfName('XRef')) {
            throw new PdfException('bad xref stream');
        }
        $raw = $this->decode($value);
        $w = array_map('intval', (array) ($value->dict['W'] ?? []));
        if (count($w) !== 3 || array_sum($w) < 1) {
            throw new PdfException('bad /W');
        }
        $index = (array) ($value->dict['Index'] ?? [0, (int) ($value->dict['Size'] ?? 0)]);
        $row = array_sum($w);
        $pos = 0;
        for ($i = 0; $i + 1 < count($index); $i += 2) {
            for ($n = (int) $index[$i], $end = $n + (int) $index[$i + 1]; $n < $end; $n++) {
                if ($pos + $row > strlen($raw)) {
                    throw new PdfException('short xref stream');
                }
                $f = [];
                $at = $pos;
                foreach ($w as $k => $width) {
                    $v = 0;
                    for ($j = 0; $j < $width; $j++) {
                        $v = ($v << 8) | ord($raw[$at++]);
                    }
                    $f[$k] = $width === 0 && $k === 0 ? 1 : $v;
                }
                $pos += $row;
                if (! isset($this->xref[$n])) {
                    $this->xref[$n] = match ($f[0]) { 1 => [1, $f[1], $f[2]], 2 => [2, $f[1], $f[2]], default => [0, 0, 0] };
                }
            }
        }
        $this->cache[$num] = $value;
        return [$value->dict, true];
    }

    private function objectAt(int $offset, int $expected): mixed
    {
        $p = new PdfParser($this->data, $offset, fn ($ref) => $this->resolve($ref));
        [$num, $value] = $p->indirectObject();
        if ($num !== $expected) {
            throw new PdfException("object $expected not at its offset");
        }
        return $value;
    }

    private function fromObjectStream(int $stream, int $num): mixed
    {
        if (! isset($this->objectStreams[$stream])) {
            $s = $this->object($stream);
            if (! $s instanceof PdfStream) {
                throw new PdfException('bad object stream');
            }
            $data = $this->decode($s);
            $first = (int) ($s->dict['First'] ?? 0);
            $count = (int) ($s->dict['N'] ?? 0);
            preg_match_all('/(\d+)\s+(\d+)/', substr($data, 0, $first), $m, PREG_SET_ORDER);
            $map = [];
            foreach (array_slice($m, 0, $count) as $pair) {
                $map[(int) $pair[1]] = $first + (int) $pair[2];
            }
            $this->objectStreams[$stream] = ['data' => $data, 'map' => $map];
        }
        $os = $this->objectStreams[$stream];
        if (! isset($os['map'][$num])) {
            throw new PdfException("object $num not in its stream");
        }
        return (new PdfParser($os['data'], $os['map'][$num]))->value();
    }

    /** a stream's data, decoded (FlateDecode, with PNG predictors) */
    public function decode(PdfStream $s): string
    {
        $filters = $s->dict['Filter'] ?? [];
        $filters = $filters instanceof PdfName ? [$filters] : (array) $this->resolve($filters);
        $params = $this->resolve($s->dict['DecodeParms'] ?? null);
        $params = is_array($params) && array_is_list($params) ? $params : [$params];
        $data = $s->data;
        foreach ($filters as $i => $filter) {
            $name = $filter instanceof PdfName ? $filter->name : '';
            if ($name !== 'FlateDecode') {
                throw new PdfException("filter $name");
            }
            $out = @gzuncompress($data);
            if ($out === false) {
                $out = @gzinflate(substr($data, 2));
            }
            if ($out === false) {
                throw new PdfException('bad flate data');
            }
            $data = $out;
            $p = is_array($params[$i] ?? null) ? $params[$i] : [];
            if ((int) ($p['Predictor'] ?? 1) >= 10) {
                $data = self::unpredict($data, (int) ($p['Columns'] ?? 1), (int) ($p['Colors'] ?? 1), (int) ($p['BitsPerComponent'] ?? 8));
            }
        }
        return $data;
    }

    /** undoes PNG row filters (cross-reference and object streams are often written with /Predictor 12) */
    private static function unpredict(string $data, int $columns, int $colors, int $bits): string
    {
        $bpp = max(1, intdiv($colors * $bits, 8));
        $rowLength = intdiv($columns * $colors * $bits + 7, 8);
        $out = '';
        $prev = str_repeat("\0", $rowLength);
        for ($pos = 0; $pos + 1 + $rowLength <= strlen($data); $pos += 1 + $rowLength) {
            $type = ord($data[$pos]);
            $row = substr($data, $pos + 1, $rowLength);
            $cur = '';
            for ($i = 0; $i < $rowLength; $i++) {
                $x = ord($row[$i]);
                $a = $i >= $bpp ? ord($cur[$i - $bpp]) : 0;
                $b = ord($prev[$i]);
                $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
                $x = match ($type) {
                    0 => $x,
                    1 => $x + $a,
                    2 => $x + $b,
                    3 => $x + intdiv($a + $b, 2),
                    4 => $x + self::paeth($a, $b, $c),
                    default => throw new PdfException('bad PNG predictor'),
                };
                $cur .= chr($x & 0xFF);
            }
            $out .= $cur;
            $prev = $cur;
        }
        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        [$pa, $pb, $pc] = [abs($p - $a), abs($p - $b), abs($p - $c)];
        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }
}
