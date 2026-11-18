<?php

namespace App\Services\Search;

use Carbon\CarbonImmutable;

/**
 * R3: what to search for, filled by the advanced search screen (R3a) or by a typed query (R3b, `φάκελος:Φ.1 …`), and
 * run by ProtocolSearch. Every condition must hold (AND). Text conditions are partial and ignore case, accents and
 * final ς (the tables' utf8mb4_general_ci collation, D05).
 */
final class SearchCriteria
{
    /** The folder value meaning "protocols without a folder" (on hold / placeholders, owner 2026-11-18) */
    public const NO_FOLDER = '-';

    /** Fields searched by partial text: protocols' columns and the attachments' ('name', 'ada') */
    public const TEXT_FIELDS = [
        'thema' => 'Θέμα',
        'in_num' => 'Αρ. εισερχομένου',
        'in_arxi_ekdosis' => 'Αρχή έκδοσης',
        'in_topos_ekdosis' => 'Τόπος έκδοσης',
        'in_paraliptis' => 'Παραλήπτης',
        'in_perilipsi' => 'Περίληψη εισερχομένου',
        'out_to' => 'Απευθύνεται',
        'out_perilipsi' => 'Περίληψη εξερχομένου',
        'sxetiko' => 'Σχετικοί αριθμοί',
        'keywords' => 'Λέξεις κλειδιά',
        'paratiriseis' => 'Παρατηρήσεις',
        'name' => 'Όνομα συνημμένου',
        'ada' => 'ΑΔΑ',
    ];

    public const ATTACHMENT_FIELDS = ['name', 'ada'];

    /** Fields that can be asked to be empty ('fakelos' = no folder, 'diekperaiosi' = nobody assigned) */
    public const EMPTY_FIELDS = ['fakelos' => 'Φάκελος', 'diekperaiosi' => 'Ανάθεση'] + self::TEXT_FIELDS;

    public const DATE_FIELDS = ['protocoldate' => 'Ημ/νία πρωτοκόλλου', 'in_date' => 'Ημ/νία εισερχομένου', 'out_date' => 'Ημ/νία εξερχομένου'];

    /** Protocol number, or the first of a range */
    public ?int $number = null;

    /** Last number of a range (null = only $number) */
    public ?int $numberTo = null;

    public ?int $year = null;

    /** @var array<string, array{0: ?CarbonImmutable, 1: ?CarbonImmutable}> date field => [from, to] (both included) */
    public array $dates = [];

    /** '' = any folder, NO_FOLDER, or a code / part of a code or of its description */
    public string $folder = '';

    /** @var array<string, string> TEXT_FIELDS key => text it must contain */
    public array $text = [];

    /** @var list<string> EMPTY_FIELDS keys that must be empty */
    public array $empty = [];

    /** User id assigned for διεκπεραίωση */
    public ?int $assigneeId = null;

    /** Part of the name of a user assigned for διεκπεραίωση */
    public ?string $assigneeName = null;

    /**
     * Pending check (owner, 2026-11-18): protocols without a folder or with ΕΚΚΡΕΜΟΤΗΤΑ in the subject (added while
     * pending, removed later)
     */
    public bool $pending = false;

    /**
     * @var list<string> R3b: free words (or "quoted phrases"); each must be found in a text field, the folder, an
     * attachment's name or ΑΔΑ, or be the protocol's number ("125" or "125/2026")
     */
    public array $words = [];

    public function isEmpty(): bool
    {
        return $this->number === null && $this->year === null && ! array_filter($this->dates, fn ($r) => $r[0] || $r[1])
            && $this->folder === '' && ! array_filter($this->text, fn ($t) => trim($t) !== '') && ! $this->empty
            && $this->assigneeId === null && ($this->assigneeName ?? '') === '' && ! $this->pending
            && ! array_filter($this->words, fn ($w) => trim($w) !== '');
    }

    /**
     * A protocol number or range as typed: "125", "120-130" (also – or spaces). Null when it isn't one.
     *
     * @return array{0: int, 1: ?int}|null
     */
    public static function parseNumber(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/^(\d{1,9})$/', $text, $m)) {
            return [(int) $m[1], null];
        }
        if (preg_match('/^(\d{1,9})\s*[-–—]\s*(\d{1,9})$/u', $text, $m)) {
            [$a, $b] = [(int) $m[1], (int) $m[2]];
            return $a <= $b ? [$a, $b] : [$b, $a];
        }

        return null;
    }
}
