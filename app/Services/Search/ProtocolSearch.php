<?php

namespace App\Services\Search;

use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Support\TagList;
use Illuminate\Database\Eloquent\Builder;

/**
 * R3: runs a SearchCriteria. Writers/readers limited to their own protocols (Ρυθμίσεις → limitProtocolAccessList)
 * only find those, as in the list. Newest first.
 *
 * Text is matched by MySQL's utf8mb4_general_ci (D05): "εκκρεμοτητα" finds "ΕΚΚΡΕΜΟΤΗΤΑ" and "Εκκρεμότητα", "οδοσ"
 * finds "οδός". % and _ typed by the user are plain characters, not wildcards.
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

        foreach ($c->text as $field => $value) {
            $value = trim($value);
            if ($value === '' || ! array_key_exists($field, SearchCriteria::TEXT_FIELDS)) {
                continue;
            }
            if (in_array($field, SearchCriteria::ATTACHMENT_FIELDS, true)) {
                $q->whereHas('attachments', fn ($a) => $a->where($field, 'LIKE', self::like($value)));
            } elseif ($field === 'sxetiko' && preg_match('~^\d+(/\d{4})?$~', $value)) {
                // a related number is matched whole: "12" finds "12/2026" and "12", not "112" or "5/2012"
                $q->where('sxetiko', 'REGEXP', '(^|[^0-9/])' . $value . '([^0-9]|$)');
            } else {
                $q->where($field, 'LIKE', self::like($value));
            }
        }

        foreach (array_unique($c->empty) as $field) {
            match (true) {
                $field === 'diekperaiosi' => $q->whereDoesntHave('assignments', fn ($a) => $a->where('type', 'd')),
                in_array($field, SearchCriteria::ATTACHMENT_FIELDS, true) => $q->whereHas('attachments', fn ($a) => self::isEmpty($a, $field)),
                array_key_exists($field, SearchCriteria::EMPTY_FIELDS) => $q->where(fn ($w) => self::isEmpty($w, $field)),
                default => null,
            };
        }

        if ($c->assigneeId !== null) {
            $q->assignedTo($c->assigneeId, 'd');
        }
        if (($name = trim((string) $c->assigneeName)) !== '') {
            $ids = User::where('name', 'LIKE', self::like($name))->pluck('id');   // unknown name → no results
            $q->whereHas('assignments', fn ($a) => $a->where('type', 'd')->whereIn('user_id', $ids));
        }

        foreach ($c->words as $word) {
            if (($word = trim($word)) !== '') {
                $q->where(fn ($w) => $this->anyField($w, $word));
            }
        }

        if ($c->pending) {
            $q->where(fn ($w) => $w->where(fn ($f) => self::isEmpty($f, 'fakelos'))->orWhere('thema', 'LIKE', '%' . self::PENDING_WORD . '%'));
        }

        return $q;
    }

    /** R3b: one free word, anywhere: a text field, the folder, an attachment, or the number (125 or 125/2026) */
    private function anyField(Builder $w, string $word): void
    {
        $like = self::like($word);
        foreach (array_diff(array_keys(SearchCriteria::TEXT_FIELDS), SearchCriteria::ATTACHMENT_FIELDS) as $field) {
            $w->orWhere($field, 'LIKE', $like);
        }
        $w->orWhere('fakelos', 'LIKE', $like)
            ->orWhereHas('attachments', fn ($a) => $a->where('name', 'LIKE', $like)->orWhere('ada', 'LIKE', $like));
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
     * The folder as chosen in the picker (a code; typed without its dots it names every folder it could be: "φ11" =
     * Φ.1.1 and Φ.11) is matched exactly;
     * other text finds every folder whose code or description contains it, or a protocol's folder containing it.
     */
    private function whereFolder(Builder $q, string $typed): void
    {
        $loose = self::loose($typed);
        $folders = Keepvalue::pluck('describe', 'fakelos');
        $exact = self::exactFolders($typed, $folders->keys()->all());
        if ($exact) {
            $q->whereIn('fakelos', $exact);
            return;
        }
        $words = preg_split('/\s+/u', TagList::fold($typed), -1, PREG_SPLIT_NO_EMPTY);
        $codes = $folders->filter(function ($describe, $code) use ($loose, $words) {
            $text = TagList::fold($code . ' ' . $describe);
            return ($loose !== '' && str_contains(self::loose((string) $code), $loose))
                || array_reduce($words, fn ($all, $w) => $all && str_contains($text, $w), true);
        })->keys()->all();
        $q->where(fn ($w) => $w->whereIn('fakelos', $codes)->orWhere('fakelos', 'LIKE', self::like($typed)));
    }

    /** R3c: the folder's code when the text names exactly one folder ("φ11" → "Φ.1.1"); otherwise the text */
    public static function folderCode(string $typed): string
    {
        $exact = self::exactFolders($typed, Keepvalue::pluck('fakelos')->all());
        return count($exact) === 1 ? $exact[0] : $typed;
    }

    /**
     * The folders a text names: the code as written (any case: "φ.1.1" = Φ.1.1 only); otherwise every code that is
     * the same without dots and spaces ("φ11" = Φ.1.1 and Φ.11)
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private static function exactFolders(string $typed, array $codes): array
    {
        $folded = TagList::fold(trim($typed));
        $same = array_values(array_filter($codes, fn ($code) => TagList::fold((string) $code) === $folded));
        if ($same) {
            return array_map('strval', $same);
        }
        $loose = self::loose($typed);
        return array_values(array_map('strval', array_filter($codes, fn ($code) => $loose !== '' && self::loose((string) $code) === $loose)));
    }

    /** Lower case without accents and without symbols or spaces: "Φ.22.1" → "φ221" */
    public static function loose(string $text): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', TagList::fold($text)) ?? '';
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
