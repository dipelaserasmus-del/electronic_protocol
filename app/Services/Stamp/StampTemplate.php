<?php

namespace App\Services\Stamp;

use App\Models\Config;
use Illuminate\Support\Facades\Storage;

/**
 * R4e: the stamp is a template, not a fixed picture: an optional emblem on top, text lines (size, bold, alignment,
 * letter spacing, narrowing) and field lines (`ΑΡΙΘΜ. ΠΡΩΤ. {αριθμός}`: the value over a dotted line), in one colour,
 * a width in mm, and where it goes on the page (corner + distance in mm, first or every page). Kept in the setting
 * "stampTemplate" (JSON); the emblem is a PNG in storage/app/stamp/ (not public). StampRenderer draws it.
 *
 * Fields in any line: {αριθμός} {ημερομηνία} {έτος} {υπηρεσία} (accents and case do not matter).
 */
final class StampTemplate
{
    public const SETTING = 'stampTemplate';

    public const EMBLEM = 'stamp/emblem.png';

    public const FIELDS = ['αριθμός' => 'number', 'ημερομηνία' => 'date', 'έτος' => 'year', 'υπηρεσία' => 'office'];

    public const CORNERS = ['top-right' => 'Πάνω δεξιά', 'top-left' => 'Πάνω αριστερά', 'bottom-right' => 'Κάτω δεξιά', 'bottom-left' => 'Κάτω αριστερά'];

    public const ALIGN = ['left' => 'Αριστερά', 'center' => 'Κέντρο', 'right' => 'Δεξιά'];

    /** limits of every number (min, max) */
    public const LIMITS = [
        'width' => [20, 120], 'emblem' => [3, 40], 'size' => [4, 24], 'spacing' => [0, 60], 'condense' => [50, 100],
        'gap' => [0, 5], 'x' => [0, 100], 'y' => [0, 100], 'lines' => [0, 12],
    ];

    public float $width = 60;

    /** #rrggbb */
    public string $color = '#1f3f99';

    public bool $border = false;

    public bool $emblem = false;

    /** emblem height, mm */
    public float $emblemHeight = 10;

    /** the emblem takes the stamp's colour (a stamp has one colour); false: its own colours */
    public bool $tint = true;

    /** extra space between lines, mm */
    public float $gap = 0.4;

    /** @var list<array{text: string, size: float, bold: bool, align: string, spacing: float, condense: float, dots: bool}> */
    public array $lines = [];

    public string $corner = 'top-right';

    /** distance from the corner, mm (x: from the side, y: from the top or bottom) */
    public float $x = 10;

    public float $y = 8;

    /** only on the first page (owner, 2026-11-24), or on every page */
    public bool $firstPageOnly = true;

    /** the editor's preview: an emblem uploaded but not saved yet (not part of the template) */
    public ?string $emblemFile = null;

    /** The default: what the stamp looked like before R4e (the office's name, number and date), top right of page 1 */
    public static function defaults(): self
    {
        $t = new self();
        $t->lines = [
            self::line('{υπηρεσία}', 8, true, 'left'),
            self::line('Αρ.Πρ.: {αριθμός} / {ημερομηνία}', 8, true, 'left'),
        ];
        $t->border = true;
        $t->color = '#0000ff';
        return $t;
    }

    public static function line(string $text = '', float $size = 8, bool $bold = false, string $align = 'center', float $spacing = 0, float $condense = 100, bool $dots = false): array
    {
        return compact('text', 'size', 'bold', 'align', 'spacing', 'condense', 'dots');
    }

    /** The saved template, or the default */
    public static function current(): self
    {
        $json = Config::getConfigValueOf(self::SETTING);
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        return is_array($data) ? self::fromArray($data) : self::defaults();
    }

    public function save(): void
    {
        Config::setConfigValueOf(self::SETTING, json_encode($this->toArray(), JSON_UNESCAPED_UNICODE));
    }

    /** From a saved / imported / edited array: every value checked and kept within its limits */
    public static function fromArray(array $a): self
    {
        $t = new self();
        $num = fn ($v, string $limit, float $default) => is_numeric($v) ? max(self::LIMITS[$limit][0], min(self::LIMITS[$limit][1], (float) $v)) : $default;
        $t->width = $num($a['width'] ?? null, 'width', $t->width);
        $t->color = is_string($a['color'] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $a['color']) ? strtolower($a['color']) : $t->color;
        $t->border = (bool) ($a['border'] ?? $t->border);
        $t->emblem = (bool) ($a['emblem'] ?? $t->emblem);
        $t->emblemHeight = $num($a['emblemHeight'] ?? null, 'emblem', $t->emblemHeight);
        $t->tint = (bool) ($a['tint'] ?? $t->tint);
        $t->gap = $num($a['gap'] ?? null, 'gap', $t->gap);
        $t->corner = array_key_exists($a['corner'] ?? '', self::CORNERS) ? $a['corner'] : $t->corner;
        $t->x = $num($a['x'] ?? null, 'x', $t->x);
        $t->y = $num($a['y'] ?? null, 'y', $t->y);
        $t->firstPageOnly = (bool) ($a['firstPageOnly'] ?? $t->firstPageOnly);
        foreach (array_slice(is_array($a['lines'] ?? null) ? $a['lines'] : [], 0, self::LIMITS['lines'][1]) as $l) {
            if (! is_array($l)) {
                continue;
            }
            $t->lines[] = self::line(
                mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($l['text'] ?? ''))), 0, 120),
                $num($l['size'] ?? null, 'size', 8),
                (bool) ($l['bold'] ?? false),
                array_key_exists($l['align'] ?? '', self::ALIGN) ? $l['align'] : 'center',
                $num($l['spacing'] ?? null, 'spacing', 0),
                $num($l['condense'] ?? null, 'condense', 100),
                (bool) ($l['dots'] ?? false),
            );
        }
        return $t;
    }

    public function toArray(): array
    {
        return [
            'width' => $this->width, 'color' => $this->color, 'border' => $this->border,
            'emblem' => $this->emblem, 'emblemHeight' => $this->emblemHeight, 'tint' => $this->tint, 'gap' => $this->gap,
            'lines' => $this->lines,
            'corner' => $this->corner, 'x' => $this->x, 'y' => $this->y, 'firstPageOnly' => $this->firstPageOnly,
        ];
    }

    /** A short fingerprint: a stamped copy made with another template (or emblem) is out of date (R4f) */
    public function hash(): string
    {
        $emblem = $this->emblem && ($path = self::emblemPath()) ? md5_file($path) : '';
        return substr(sha1(json_encode($this->toArray()) . $emblem), 0, 16);
    }

    /** The emblem file, when there is one */
    public static function emblemPath(): ?string
    {
        return Storage::exists(self::EMBLEM) ? Storage::path(self::EMBLEM) : null;
    }

    /** RGB of the colour */
    public function rgb(): array
    {
        return array_map('hexdec', str_split(substr($this->color, 1), 2));
    }

    /**
     * A line's text with its fields filled in. Unknown {…} stay as typed.
     *
     * @param array{number?: string, date?: string, year?: string, office?: string} $values
     */
    public static function fill(string $text, array $values): string
    {
        return preg_replace_callback('/\{([^{}]{1,20})\}/u', function ($m) use ($values) {
            $field = self::field($m[1]);
            return $field === null ? $m[0] : (string) ($values[$field] ?? '');
        }, $text);
    }

    /** The fields a line shows ('number', 'date', …), read as fill() reads them (any case, accents, spaces) */
    public static function fieldsIn(string $text): array
    {
        preg_match_all('/\{([^{}]{1,20})\}/u', $text, $m);
        return array_values(array_unique(array_filter(array_map(self::field(...), $m[1]))));
    }

    /** a placeholder's field, or null: {ΑΡΙΘΜΟΣ}, { αριθμός } and {αριθμος} are all the number */
    private static function field(string $placeholder): ?string
    {
        $key = \App\Support\TagList::fold(trim($placeholder));
        foreach (self::FIELDS as $name => $field) {
            if (\App\Support\TagList::fold($name) === $key) {
                return $field;
            }
        }
        return null;
    }

    /** Values of a protocol for the fields */
    public static function valuesFor(\App\Models\Protocol $protocol): array
    {
        $date = $protocol->dateOf('protocoldate') ?? now();
        return [
            'number' => (string) $protocol->protocolnum,
            'date' => $date->format('d/m/Y'),
            'year' => (string) ($protocol->etos ?: $date->format('Y')),
            'office' => (string) Config::getConfigValueOf('ipiresiasName'),
        ];
    }

    /** Example values for the editor's preview */
    public static function sampleValues(): array
    {
        return ['number' => '1234', 'date' => now()->format('d/m/Y'), 'year' => now()->format('Y'), 'office' => (string) Config::getConfigValueOf('ipiresiasName')];
    }
}
