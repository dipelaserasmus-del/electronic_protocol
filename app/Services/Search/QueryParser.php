<?php

namespace App\Services\Search;

use App\Support\DateInput;
use App\Support\TagList;
use Carbon\CarbonImmutable;

/**
 * R3b: a Gmail-style search text → SearchCriteria, and back (format()).
 *
 *   αίτηση μετάθεσης από:δήμος φάκελος:Φ.1 έτος:2026 θέμα:"άδεια μητρότητας"
 *
 * Words without an operator must all be found somewhere (any text field, the folder, an attachment, or the number).
 * "Quoted text" is one word. Operator names ignore case and accents (φακελος: = Φάκελος:) and have short and English
 * forms (φακ:, folder:). An unknown operator is an ordinary word ("ώρα:10" is searched as text). A known operator
 * with a value it cannot use (έτος:abc) is reported in $ignored and left out.
 */
final class QueryParser
{
    /** operator (folded) => what it sets; the first name of each is the one format() writes */
    private const OPERATORS = [
        'αρ' => 'number', 'αριθμοσ' => 'number', 'num' => 'number',
        'ετοσ' => 'year', 'year' => 'year',
        'ημ' => 'date:protocoldate', 'ημερομηνια' => 'date:protocoldate', 'date' => 'date:protocoldate',
        'ημεισ' => 'date:in_date', 'ημεξ' => 'date:out_date',
        'φακελοσ' => 'folder', 'φακ' => 'folder', 'folder' => 'folder',
        'θεμα' => 'text:thema', 'subject' => 'text:thema',
        'απο' => 'text:in_arxi_ekdosis', 'from' => 'text:in_arxi_ekdosis',
        'προσ' => 'text:out_to', 'to' => 'text:out_to',
        'εισ' => 'text:in_num', 'εισερχομενο' => 'text:in_num',
        'τοποσ' => 'text:in_topos_ekdosis',
        'παραληπτησ' => 'text:in_paraliptis',
        'περιληψη' => 'text:in_perilipsi',
        'περιληψηεξ' => 'text:out_perilipsi',
        'σχετ' => 'text:sxetiko', 'σχετικοι' => 'text:sxetiko', 'σχετικο' => 'text:sxetiko',
        'λεξη' => 'text:keywords', 'λεξεισ' => 'text:keywords',
        'παρατηρησεισ' => 'text:paratiriseis', 'σημ' => 'text:paratiriseis',
        'συνημμενο' => 'text:name', 'αρχειο' => 'text:name', 'file' => 'text:name',
        'αδα' => 'text:ada', 'ada' => 'text:ada',
        'αναθεση' => 'assignee', 'assignee' => 'assignee',
        'κενο' => 'empty', 'empty' => 'empty',
        'ειναι' => 'is', 'is' => 'is',
    ];

    /** Names written by format() (and shown in the help), per target */
    public const NAMES = [
        'number' => 'αρ', 'year' => 'έτος', 'date:protocoldate' => 'ημ', 'date:in_date' => 'ημεισ', 'date:out_date' => 'ημεξ', 'folder' => 'φάκελος', 'text:thema' => 'θέμα',
        'text:in_arxi_ekdosis' => 'από', 'text:out_to' => 'προς', 'text:in_num' => 'εισ', 'text:in_topos_ekdosis' => 'τόπος',
        'text:in_paraliptis' => 'παραλήπτης', 'text:in_perilipsi' => 'περίληψη', 'text:out_perilipsi' => 'περίληψηεξ',
        'text:sxetiko' => 'σχετ', 'text:keywords' => 'λέξη', 'text:paratiriseis' => 'παρατηρήσεις', 'text:name' => 'συνημμένο',
        'text:ada' => 'αδα', 'assignee' => 'ανάθεση', 'empty' => 'κενό', 'is' => 'είναι',
    ];

    /** Values meaning "none" (φάκελος:κανένας, ανάθεση:κανένας) and "every year" (έτος:όλα), folded */
    private const NONE = ['κανενασ', 'κανενα', 'χωρισ', 'none', '-'];
    private const ALL = ['ολα', 'all', '*'];
    private const PENDING = ['εκκρεμεσ', 'εκκρεμη', 'εκκρεμοτητα', 'pending'];

    /** field names accepted by κενό: (folded) */
    private const EMPTY_NAMES = ['φακελοσ' => 'fakelos', 'αναθεση' => 'diekperaiosi'];

    /** @var list<string> what was left out, as typed, with the reason */
    public array $ignored = [];

    /** the text set the year (έτος:…, also έτος:όλα, or αρ:N/YYYY) */
    public bool $yearGiven = false;

    public function parse(string $text, ?CarbonImmutable $today = null): SearchCriteria
    {
        $this->ignored = [];
        $this->yearGiven = false;
        $c = new SearchCriteria();
        foreach (self::tokens($text) as [$op, $value, $raw]) {
            $target = $op === null ? null : (self::OPERATORS[TagList::fold($op)] ?? null);
            if ($target === null) {
                $c->words[] = $raw;
                continue;
            }
            if ($value === '') {
                $this->ignored[] = "«{$raw}»: χωρίς τιμή";
                continue;
            }
            if (! $this->apply($c, $target, $value, $today)) {
                $this->ignored[] = "«{$raw}»: " . $this->hint($target);
            }
        }
        $c->words = array_values(array_filter($c->words, fn ($w) => $w !== ''));
        return $c;
    }

    private function apply(SearchCriteria $c, string $target, string $value, ?CarbonImmutable $today): bool
    {
        $folded = TagList::fold($value);
        if (str_starts_with($target, 'text:')) {
            $field = substr($target, 5);
            if (isset($c->text[$field])) {
                return false;   // one value per field (the first one counts)
            }
            $c->text[$field] = $value;
            return true;
        }
        switch ($target) {
            case 'number':
                if (preg_match('~^(\d{1,9})/(\d{4})$~', $value, $m)) {
                    [$c->number, $c->numberTo, $c->year] = [(int) $m[1], null, (int) $m[2]];
                    $this->yearGiven = true;
                    return true;
                }
                $n = SearchCriteria::parseNumber($value);
                if ($n) {
                    [$c->number, $c->numberTo] = $n;
                }
                return (bool) $n;
            case 'year':
                if (in_array($folded, self::ALL, true)) {
                    $c->year = null;
                    return $this->yearGiven = true;
                }
                if (preg_match('/^\d{4}$/', $value)) {
                    $c->year = (int) $value;
                    return $this->yearGiven = true;
                }
                return false;
            case 'date:protocoldate':
            case 'date:in_date':
            case 'date:out_date':
                $range = self::dateRange($value, $today);
                if ($range) {
                    $c->dates[substr($target, 5)] = $range;
                }
                return (bool) $range;
            case 'folder':
                $c->folder = in_array($folded, self::NONE, true) ? SearchCriteria::NO_FOLDER : $value;
                return true;
            case 'assignee':
                if (in_array($folded, self::NONE, true)) {
                    $c->empty[] = 'diekperaiosi';
                } else {
                    $c->assigneeName = $value;
                }
                return true;
            case 'empty':
                $field = self::EMPTY_NAMES[$folded] ?? self::textField($folded);
                if ($field === null) {
                    return false;
                }
                $c->empty[] = $field;
                return true;
            case 'is':
                if (in_array($folded, self::PENDING, true)) {
                    $c->pending = true;
                    return true;
                }
                return false;
        }
        return false;
    }

    private function hint(string $target): string
    {
        return match ($target) {
            'number' => 'αριθμός, από-έως ή αριθμός/έτος (π.χ. αρ:125, αρ:120-130, αρ:125/2026)',
            'year' => 'τετραψήφιο έτος ή «όλα»',
            'date:protocoldate', 'date:in_date', 'date:out_date' => 'ημερομηνία ή διάστημα (π.χ. ημ:9/1/2026, ημ:1/1/2026..31/3/2026)',
            'empty' => 'όνομα πεδίου (π.χ. κενό:φάκελος, κενό:θέμα)',
            'is' => 'μόνο είναι:εκκρεμές',
            default => str_starts_with($target, 'text:') ? 'μία τιμή ανά πεδίο (βάλτε τη φράση σε εισαγωγικά)' : 'μη έγκυρη τιμή',
        };
    }

    /** a text field by its operator name (κενό:θέμα) */
    private static function textField(string $folded): ?string
    {
        $t = self::OPERATORS[$folded] ?? null;
        return $t !== null && str_starts_with($t, 'text:') ? substr($t, 5) : null;
    }

    /**
     * One day (also short: 9/1), a range with ".." (either side may be left empty) or " - " / "-" between two dates.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}|null
     */
    public static function dateRange(string $value, ?CarbonImmutable $today = null): ?array
    {
        if (str_contains($value, '..')) {
            [$a, $b] = array_map('trim', explode('..', $value, 2));
        } elseif (preg_match('~^(\S+/\S+)\s*-\s*(\S+/\S+)$~', $value, $m)) {
            [$a, $b] = [$m[1], $m[2]];
        } else {
            $a = $b = $value;
        }
        $parse = function (string $t) use ($today) {
            if ($t === '') {
                return false;   // open end
            }
            $v = DateInput::expand($t, $today);
            return $v ? CarbonImmutable::createFromFormat('!' . DateInput::FORMAT, $v) : null;
        };
        [$from, $to] = [$parse($a), $parse($b)];
        if ($from === null || $to === null || ($from === false && $to === false)) {
            return null;
        }
        [$from, $to] = [$from ?: null, $to ?: null];
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    /**
     * The search text split in [operator|null, value, as typed]: words, "quoted phrases", op:value, op:"quoted value".
     *
     * @return list<array{0: ?string, 1: string, 2: string}>
     */
    public static function tokens(string $text): array
    {
        preg_match_all('/([\p{L}\p{N}_]+):(?:"([^"]*)"?|(\S*))|"([^"]*)"?|(\S+)/u', $text, $all, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $out = [];
        foreach ($all as $m) {
            if ($m[1] !== null) {
                $out[] = [$m[1], trim($m[2] ?? $m[3] ?? ''), $m[0]];
            } elseif ($m[4] !== null) {
                $out[] = [null, trim($m[4]), trim($m[4])];
            } else {
                $out[] = [null, $m[5], $m[5]];
            }
        }
        return $out;
    }

    /** SearchCriteria → search text, so parse(format($c)) gives the same criteria (shown on the advanced screen). */
    public static function format(SearchCriteria $c): string
    {
        // quoted when it has spaces, or when it would be read back as a search word ("θέμα:άδεια" typed in quotes)
        $q = static fn (string $v) => preg_match('/[\s"]|^[\p{L}\p{N}_]+:/u', $v) || $v === '' ? '"' . str_replace('"', '', $v) . '"' : $v;
        $parts = array_map($q, $c->words);
        if ($c->number !== null) {
            $parts[] = self::NAMES['number'] . ':' . $c->number . ($c->numberTo !== null ? '-' . $c->numberTo : '');
        }
        if ($c->year !== null) {
            $parts[] = self::NAMES['year'] . ':' . $c->year;
        }
        foreach (SearchCriteria::DATE_FIELDS as $field => $_) {
            if (($range = $c->dates[$field] ?? null) && ($range[0] || $range[1])) {
                [$from, $to] = [$range[0]?->format('d/m/Y') ?? '', $range[1]?->format('d/m/Y') ?? ''];
                $parts[] = self::NAMES["date:$field"] . ':' . ($from === $to ? $from : "$from..$to");
            }
        }
        if ($c->folder !== '') {
            $parts[] = self::NAMES['folder'] . ':' . ($c->folder === SearchCriteria::NO_FOLDER ? 'κανένας' : $q($c->folder));
        }
        foreach ($c->text as $field => $value) {
            if (trim($value) !== '' && isset(self::NAMES["text:$field"])) {
                $parts[] = self::NAMES["text:$field"] . ':' . $q(trim($value));
            }
        }
        $assignee = $c->assigneeName ?? ($c->assigneeId !== null ? \App\Models\User::find($c->assigneeId)?->name : null);
        if (($assignee ?? '') !== '') {
            $parts[] = self::NAMES['assignee'] . ':' . $q($assignee);
        }
        foreach (array_unique($c->empty) as $field) {
            $parts[] = match ($field) {
                'diekperaiosi' => self::NAMES['assignee'] . ':κανένας',
                'fakelos' => self::NAMES['empty'] . ':φάκελος',
                default => self::NAMES['empty'] . ':' . self::NAMES["text:$field"],
            };
        }
        if ($c->pending) {
            $parts[] = self::NAMES['is'] . ':εκκρεμές';
        }
        return implode(' ', $parts);
    }
}
