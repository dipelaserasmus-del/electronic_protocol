<?php

namespace App\Services\Search;

use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Support\FolderCode;
use App\Support\TagList;
use Illuminate\Database\Eloquent\Builder;

/**
 * R3: runs a SearchCriteria. Writers/readers limited to their own protocols (Ρυθμίσεις → limitProtocolAccessList)
 * only find those, as in the list. Newest first.
 *
 * Text is matched by MySQL's utf8mb4_general_ci (D05): "εκκρεμοτητα" finds "ΕΚΚΡΕΜΟΤΗΤΑ" and "Εκκρεμότητα", "οδοσ"
 * finds "οδός". % and _ typed by the user are plain characters, not wildcards.
 *
 * R3f (owner, 2026-11-20): a value starting with "=" is matched as a whole word (or number): "=120" finds "120",
 * "120/3" and "αρ. 120", not "234120" or "1205". Case, accents and final ς are still ignored.
 */
final class ProtocolSearch
{
    /** The word marking a pending protocol in its subject (any case/accents, also ΕΚΚΡΕΜΟΤΗΤΕΣ) */
    public const PENDING_WORD = 'εκκρεμοτητ';

    public function query(SearchCriteria $c, User $user): Builder
    {
        $q = Protocol::query()->with('attachments', 'assignments')->orderByDesc('etos')->orderByDesc('protocolnum');
        if (ProtocolPolicy::isLimited($user)) {
            $q->ownOrAssigned((int) $user->id);
        }

        if ($c->number !== null) {
            $c->numberTo === null ? $q->where('protocolnum', $c->number) : $q->whereBetween('protocolnum', [$c->number, $c->numberTo]);
        }
        if ($c->year !== null) {
            $q->where('etos', $c->year);
        }
        foreach ($c->dates as $field => [$from, $to]) {
            if (! array_key_exists($field, SearchCriteria::DATE_FIELDS)) {
                continue;
            }
            if ($from) {
                $q->where($field, '>=', $from->format('Y-m-d'));   // D04: DATE columns
            }
            if ($to) {
                $q->where($field, '<=', $to->format('Y-m-d'));
            }
        }

        if ($c->folder === SearchCriteria::NO_FOLDER) {
            $q->where(fn ($w) => self::isEmpty($w, 'fakelos'));
        } elseif (trim($c->folder) !== '') {
            $this->whereFolder($q, trim($c->folder));
        }

        // conditions on attachments: all of them on the same attachment (review of R3a: a name and an ΑΔΑ found on two
        // different attachments of a protocol is not a match)
        $onAttachment = [];

        foreach ($c->text as $field => $value) {
            $value = trim($value);
            if ($value === '' || ! array_key_exists($field, SearchCriteria::TEXT_FIELDS)) {
                continue;
            }
            [$value, $whole] = self::wholeOf($value);
            if ($value === '') {
                continue;
            }
            if (in_array($field, SearchCriteria::ATTACHMENT_FIELDS, true)) {
                $onAttachment[] = fn ($a) => self::contains($a, $field, $value, $whole);
            } elseif ($field === 'sxetiko' && preg_match('~^\d+(/\d{4})?$~', $value)) {
                // a related number is matched whole: "12" finds "12/2026" and "12", not "112" or "5/2012"
                $q->where('sxetiko', 'REGEXP', '(^|[^0-9/])' . $value . '([^0-9]|$)');
                if ($whole) {
                    self::contains($q, 'sxetiko', $value, true);   // and no letter next to it: not "Α120" (review of R3f)
                }
            } else {
                self::contains($q, $field, $value, $whole);
            }
        }

        foreach (array_unique($c->empty) as $field) {
            match (true) {
                $field === 'diekperaiosi' => $q->whereDoesntHave('assignments', fn ($a) => $a->where('type', 'd')),
                in_array($field, SearchCriteria::ATTACHMENT_FIELDS, true) => $onAttachment[] = fn ($a) => self::isEmpty($a, $field),
                array_key_exists($field, SearchCriteria::EMPTY_FIELDS) => $q->where(fn ($w) => self::isEmpty($w, $field)),
                default => null,
            };
        }

        if ($onAttachment) {
            $q->whereHas('attachments', function ($a) use ($onAttachment) {
                foreach ($onAttachment as $condition) {
                    $condition($a);
                }
            });
        }

        if ($c->assigneeId !== null) {
            $q->assignedTo($c->assigneeId, 'd');
        }
        if (($name = trim((string) $c->assigneeName)) !== '') {
            $ids = User::where('name', 'LIKE', self::like($name))->pluck('id');   // unknown name → no results
            $q->whereHas('assignments', fn ($a) => $a->where('type', 'd')->whereIn('user_id', $ids));
        }

        foreach ($c->words as $word) {
            [$word, $whole] = self::wholeOf(trim($word));
            if ($word !== '') {
                $q->where(fn ($w) => $this->anyField($w, $word, $whole));
            }
        }

        if ($c->pending) {
            $q->where(fn ($w) => $w->where(fn ($f) => self::isEmpty($f, 'fakelos'))->orWhere('thema', 'LIKE', '%' . self::PENDING_WORD . '%'));
        }

        return $q;
    }

    /** R3b: one free word, anywhere: a text field, the folder, an attachment, or the number (125 or 125/2026) */
    private function anyField(Builder $w, string $word, bool $whole = false): void
    {
        foreach (array_diff(array_keys(SearchCriteria::TEXT_FIELDS), SearchCriteria::ATTACHMENT_FIELDS) as $field) {
            $w->orWhere(fn ($f) => self::contains($f, $field, $word, $whole));
        }
        $w->orWhere(fn ($f) => self::contains($f, 'fakelos', $word, $whole))
            ->orWhereHas('attachments', fn ($a) => $a->where(fn ($x) => self::contains($x, 'name', $word, $whole))
                ->orWhere(fn ($x) => self::contains($x, 'ada', $word, $whole)));
        if (preg_match('~^(\d{1,9})(?:/(\d{4}))?$~', $word, $m)) {
            $w->orWhere(fn ($n) => isset($m[2]) ? $n->where('protocolnum', (int) $m[1])->where('etos', (int) $m[2]) : $n->where('protocolnum', (int) $m[1]));
        }
    }

    /** Why a protocol is pending (for the badges of the pending check): 'folder' and/or 'subject' */
    public static function pendingReasons(Protocol $p): array
    {
        return array_keys(array_filter([
            'folder' => trim((string) $p->fakelos) === '',
            'subject' => str_contains(TagList::fold((string) $p->thema), self::PENDING_WORD),
        ]));
    }

    /**
     * R1h (owner, 2026-11-18): a folder's code (case, accents and the dots before the number optional, the dots inside
     * the number not: "φ11" = Φ.11 and Φ11, never Φ1.1; "12" = Φ12) is matched exactly. Other text finds every folder
     * whose code starts with it ("φ1." → Φ1.1, Φ1.2) or whose code and description contain all its words, and also a
     * protocol's folder containing it (old codes missing from the list).
     */
    private function whereFolder(Builder $q, string $typed): void
    {
        $folders = Keepvalue::pluck('describe', 'fakelos');
        $exact = self::exactFolders($typed, $folders->keys()->all());
        if ($exact) {
            $q->whereIn('fakelos', $exact);
            return;
        }
        $words = preg_split('/\s+/u', TagList::fold($typed), -1, PREG_SPLIT_NO_EMPTY);
        $codes = $folders->filter(function ($describe, $code) use ($typed, $words) {
            $text = TagList::fold($code . ' ' . $describe);
            return FolderCode::startsWith($typed, (string) $code)
                || array_reduce($words, fn ($all, $w) => $all && str_contains($text, $w), true);
        })->keys()->all();
        $q->where(fn ($w) => $w->whereIn('fakelos', $codes)->orWhere('fakelos', 'LIKE', self::like($typed)));
    }

    /** R3c: the folder's code when the text names exactly one folder ("φ.1.1" → "Φ.1.1"); otherwise the text */
    public static function folderCode(string $typed): string
    {
        $exact = self::exactFolders($typed, Keepvalue::pluck('fakelos')->all());
        return count($exact) === 1 ? $exact[0] : $typed;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string> the codes the text names (FolderCode::same)
     */
    private static function exactFolders(string $typed, array $codes): array
    {
        return array_values(array_map('strval', array_filter($codes, fn ($code) => FolderCode::same($typed, (string) $code))));
    }

    /** R3f: "=120" → ['120', true] (whole word); "120" → ['120', false] */
    public static function wholeOf(string $value): array
    {
        return str_starts_with($value, '=') ? [trim(substr($value, 1)), true] : [$value, false];
    }

    /** The column contains the text; whole (R3f): as a word of its own, not inside a longer word or number. */
    private static function contains($query, string $column, string $value, bool $whole)
    {
        // whole: the regexp alone, which ignores case and accents itself (review of R3f: also Latin accents and accents
        // stored as separate marks, which the collation's LIKE does not always match)
        return $whole
            ? $query->whereRaw("REGEXP_LIKE(`{$column}`, ?, 'i')", [self::wordPattern($value)])
            : $query->where($column, 'LIKE', self::like($value));   // the collation's matching (case, accents)
    }

    /**
     * Accented / final forms of a base letter, for the whole-word pattern (the regexp does not fold accents): Greek,
     * and the Latin letters of the collation's folding (review of R3f: "=cafe" finds "café")
     */
    private const VARIANTS = [
        'α' => 'αά', 'ε' => 'εέ', 'η' => 'ηή', 'ι' => 'ιίϊΐ', 'ο' => 'οό', 'υ' => 'υύϋΰ', 'ω' => 'ωώ', 'σ' => 'σς',
        'a' => 'aàáâãäåā', 'c' => 'cçćč', 'e' => 'eèéêëēěę', 'i' => 'iìíîïī', 'n' => 'nñńň', 'o' => 'oòóôõöøō',
        'u' => 'uùúûüūů', 'y' => 'yýÿ', 's' => 'sśš', 'z' => 'zźżž', 'r' => 'rř', 'd' => 'dď', 't' => 'tť', 'l' => 'lł',
    ];

    /** R3f: ICU pattern of the text as a whole word: no letter or digit just before or after it */
    public static function wordPattern(string $value): string
    {
        $body = '';
        foreach (mb_str_split(self::baseLetters($value)) as $ch) {
            if (preg_match('/\s/u', $ch)) {
                $body .= '\\s+';
            } elseif (preg_match('/\p{L}/u', $ch)) {
                // any accented form of the letter, also with its accents as separate marks (decomposed)
                $body .= '[' . (self::VARIANTS[$ch] ?? $ch) . ']\\p{M}*';
            } else {
                $body .= preg_match('/\p{N}/u', $ch) ? $ch : '\\' . $ch;
            }
        }

        // an accent stored as a separate mark is part of the word, never a boundary (review of R3f)
        return '(^|[^\\p{L}\\p{N}\\p{M}])' . $body . '([^\\p{L}\\p{N}\\p{M}]|$)';
    }

    /** Lower case, without accents or final forms: "Άδεια", "άδεια" (decomposed), "ΆΔΕΙΑ" → "αδεια"; "Café" → "cafe" */
    private static function baseLetters(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if (class_exists(\Normalizer::class)) {
            $value = preg_replace('/\p{M}+/u', '', (string) \Normalizer::normalize($value, \Normalizer::FORM_D));
        } else {
            $value = strtr($value, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ϊ' => 'ι', 'ΐ' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ώ' => 'ω']);
        }

        return str_replace('ς', 'σ', $value);
    }

    /** "contains" pattern for LIKE; the user's % _ \ are plain characters */
    public static function like(string $value): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
    }

    private static function isEmpty($query, string $column)
    {
        return $query->where(fn ($w) => $w->whereNull($column)->orWhere($column, ''));
    }
}
