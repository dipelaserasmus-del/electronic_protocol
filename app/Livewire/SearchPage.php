<?php

namespace App\Livewire;

use App\Models\Config;
use App\Models\User;
use App\Services\Search\ProtocolSearch;
use App\Services\Search\QueryParser;
use App\Services\Search\SearchCriteria;
use App\Support\DateInput;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R3a: the advanced search screen at /find (replaces the old jQuery page and its /getFindData results).
 * The year is filled in with the current one and may be cleared (all years); Enter in any field searches; text fields
 * match part of the text, ignoring case and accents; the folder picker finds a folder by code (dots optional) or by a
 * word of its description, and offers «Χωρίς φάκελο». The pending check (owner, 2026-11-18) lists the protocols of
 * a date range without a folder or with ΕΚΚΡΕΜΟΤΗΤΑ in the subject, and says so clearly when there are none.
 * Every field is in the address, so a search can be bookmarked, reloaded and opened from R3c's results page.
 */
class SearchPage extends Component
{
    use WithPagination;
    use \App\Livewire\Concerns\PagesWithinRange;

    protected string $paginationTheme = 'bootstrap';

    /** Text fields always on screen; the others are under "Περισσότερα πεδία" */
    public const MAIN_TEXT = ['thema', 'in_num', 'in_arxi_ekdosis', 'out_to', 'sxetiko', 'keywords'];

    #[Url]
    public string $num = '';

    #[Url]
    public string $year = '';

    /** protocol date range (also the range of the pending check) */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'in_from')]
    public string $inFrom = '';

    #[Url(as: 'in_to')]
    public string $inTo = '';

    #[Url(as: 'out_from')]
    public string $outFrom = '';

    #[Url(as: 'out_to_date')]
    public string $outTo = '';

    /** '' any, SearchCriteria::NO_FOLDER, or a folder code */
    #[Url]
    public string $folder = '';

    /** @var array<string, string> SearchCriteria::TEXT_FIELDS key => text */
    #[Url(as: 't')]
    public array $text = [];

    /** @var list<string> SearchCriteria::EMPTY_FIELDS keys */
    #[Url]
    public array $empty = [];

    /** '' anybody, 'none' nobody assigned, or a user id (assigned for διεκπεραίωση) */
    #[Url]
    public string $assignee = '';

    #[Url]
    public bool $pending = false;

    /** R3b: words found anywhere ("quoted phrase" = one) */
    #[Url(as: 'w')]
    public string $words = '';

    /** R3f (owner, 2026-11-20): every text and word matched whole ("120" finds 120, not 234120 or 1205) */
    #[Url(as: 'whole')]
    public bool $whole = false;

    /** a search was asked for (the results are shown) */
    #[Url(as: 's')]
    public bool $searched = false;

    public function mount(): void
    {
        if (! request()->query->has('year')) {
            $this->year = (string) self::currentYear();
        }
        $this->text = array_intersect_key(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $this->text), SearchCriteria::TEXT_FIELDS);
        $this->empty = array_values(array_intersect(array_map('strval', $this->empty), array_keys(SearchCriteria::EMPTY_FIELDS)));
    }

    public static function currentYear(): int
    {
        return (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
    }

    public function search(): void
    {
        $this->searched = true;
        $this->resetPage();
    }

    /**
     * The pending check: only the year and the protocol date range are kept, so "nothing pending" is never the
     * result of another filter left in a field
     */
    public function pendingCheck(): void
    {
        $this->reset('num', 'inFrom', 'inTo', 'outFrom', 'outTo', 'folder', 'text', 'empty', 'assignee', 'words');
        $this->pending = true;
        $this->search();
    }

    /** Only the pending check, the year and the protocol dates: an empty result means nothing is pending there */
    public static function onlyPendingRange(SearchCriteria $c): bool
    {
        $rest = clone $c;
        $rest->pending = false;
        $rest->year = null;
        unset($rest->dates['protocoldate']);
        return $c->pending && $rest->isEmpty();
    }

    /**
     * R3c: the advanced screen's address for these criteria ("Σύνθετη αναζήτηση" on the results page). What has no
     * field of its own (free words, an assignee by name) goes to «Οπουδήποτε» as search words.
     *
     * @return array<string, mixed>
     */
    public static function paramsFor(SearchCriteria $c): array
    {
        $date = fn (string $field, int $i) => ($c->dates[$field][$i] ?? null)?->format(DateInput::FORMAT) ?? '';
        $anywhere = new SearchCriteria();
        $anywhere->words = $c->words;
        $anywhere->assigneeName = $c->assigneeName;
        $empty = array_values(array_diff($c->empty, ['diekperaiosi']));

        return array_filter([
            's' => 1,
            'year' => (string) ($c->year ?? ''),   // empty: every year (the screen fills in this year only when it is missing)
            'num' => $c->number === null ? '' : $c->number . ($c->numberTo !== null ? '-' . $c->numberTo : ''),
            'from' => $date('protocoldate', 0), 'to' => $date('protocoldate', 1),
            'in_from' => $date('in_date', 0), 'in_to' => $date('in_date', 1),
            'out_from' => $date('out_date', 0), 'out_to_date' => $date('out_date', 1),
            'folder' => $c->folder === SearchCriteria::NO_FOLDER ? $c->folder : ProtocolSearch::folderCode($c->folder),
            't' => $c->text,
            'empty' => $empty,
            'assignee' => in_array('diekperaiosi', $c->empty, true) ? 'none' : (string) ($c->assigneeId ?? ''),
            'pending' => $c->pending ? 1 : '',
            'w' => QueryParser::format($anywhere),
        ], fn ($v, $k) => $k === 'year' || ($v !== '' && $v !== []), ARRAY_FILTER_USE_BOTH);
    }

    /** Builds the criteria from the fields; wrong fields get an error and give null. */
    public function criteria(): ?SearchCriteria
    {
        $this->resetErrorBag();
        $c = new SearchCriteria();

        if (trim($this->num) !== '') {
            $n = SearchCriteria::parseNumber($this->num);
            if ($n) {
                [$c->number, $c->numberTo] = $n;
            } else {
                $this->addError('num', 'Ένας αριθμός (π.χ. 125) ή από–έως (π.χ. 120-130).');
            }
        }
        if (trim($this->year) !== '') {
            if (preg_match('/^\d{4}$/', trim($this->year))) {
                $c->year = (int) trim($this->year);
            } else {
                $this->addError('year', 'Τέσσερα ψηφία (π.χ. 2026) ή κενό για όλα τα έτη.');
            }
        }
        foreach (['protocoldate' => ['from', 'to'], 'in_date' => ['inFrom', 'inTo'], 'out_date' => ['outFrom', 'outTo']] as $field => [$a, $b]) {
            $range = [$this->date($a), $this->date($b)];
            if ($range[0] && $range[1] && $range[0]->gt($range[1])) {
                $this->addError($b, 'Η ημερομηνία «έως» είναι πριν από την «από».');
            }
            if ($range[0] || $range[1]) {
                $c->dates[$field] = $range;
            }
        }
        $c->folder = trim($this->folder);
        $c->text = array_filter(array_map('trim', $this->text), fn ($v) => $v !== '');
        $c->empty = $this->empty;
        if ($this->assignee === 'none') {
            $c->empty[] = 'diekperaiosi';
        } elseif (ctype_digit($this->assignee)) {
            $c->assigneeId = (int) $this->assignee;
        }
        $c->pending = $this->pending;
        // R3b: «Οπουδήποτε» also takes the search text's operators (φάκελος:Φ.1 …); a field filled in on the form wins
        $parser = new QueryParser();
        $typed = $parser->parse($this->words);
        if ($parser->ignored) {
            $this->addError('words', 'Δεν χρησιμοποιήθηκαν: ' . implode(' · ', $parser->ignored));
        }
        $c->words = $typed->words;
        if ($c->number === null && $typed->number !== null) {
            [$c->number, $c->numberTo] = [$typed->number, $typed->numberTo];
        }
        if ($parser->yearGiven) {
            $c->year = $typed->year;   // typed (έτος:2025, έτος:όλα, αρ:125/2025): wins over the prefilled year
        }
        $c->dates += $typed->dates;
        $c->folder = $c->folder !== '' ? $c->folder : $typed->folder;
        $c->text += $typed->text;
        // the assignee chosen in the menu wins over ανάθεση: typed in «Οπουδήποτε» (review of R3b)
        $typedEmpty = $this->assignee === '' ? $typed->empty : array_diff($typed->empty, ['diekperaiosi']);
        $c->empty = array_values(array_unique([...$c->empty, ...$typedEmpty]));
        $c->assigneeName = $this->assignee === '' ? $typed->assigneeName : null;
        $c->pending = $c->pending || $typed->pending;
        if ($this->whole) {   // R3f: as if each value were typed with "=" (ProtocolSearch::wholeOf)
            $eq = fn (string $v) => str_starts_with($v, '=') ? $v : '=' . $v;
            $c->text = array_map($eq, $c->text);
            $c->words = array_map($eq, $c->words);
        }

        return $this->getErrorBag()->isEmpty() ? $c : null;
    }

    /** dd/mm/yyyy (also "9", "9/4" … as in every date field, N04); a wrong date is an error on the field */
    private function date(string $property): ?CarbonImmutable
    {
        $typed = trim($this->{$property});
        if ($typed === '') {
            return null;
        }
        $value = DateInput::expand($typed);
        if (! $value) {
            $this->addError($property, 'Μη έγκυρη ημερομηνία (π.χ. 9, 9/4 ή 09/04/2026).');
            return null;
        }
        $this->{$property} = $value;
        return CarbonImmutable::createFromFormat('!' . DateInput::FORMAT, $value);
    }

    /** "από 01/01/2026 έως 31/03/2026", "το 2026", "σε όλα τα έτη": the range of the pending check, in words */
    public function rangeText(SearchCriteria $c): string
    {
        [$from, $to] = $c->dates['protocoldate'] ?? [null, null];
        $parts = array_filter([$from ? 'από ' . $from->format('d/m/Y') : null, $to ? 'έως ' . $to->format('d/m/Y') : null]);
        if ($parts) {
            return implode(' ', $parts) . ($c->year ? " (έτος {$c->year})" : '');
        }
        return $c->year ? "το {$c->year}" : 'σε όλα τα έτη';
    }

    public function render()
    {
        $criteria = $this->searched ? $this->criteria() : null;
        $protocols = null;
        if ($criteria && ! $criteria->isEmpty()) {
            $perPage = (int) (Config::getConfigValueOf('maxRowsInFindPage') ?: 30);
            $protocols = $this->pageOf(app(ProtocolSearch::class)->query($criteria, Auth::user()), $perPage);
        }

        return view('livewire.search-page', [
            'criteria' => $criteria,
            'asText' => $criteria && ! $criteria->isEmpty() ? QueryParser::format($criteria) : null,
            'protocols' => $protocols,
            'markWords' => $criteria?->words ?? [],
            'terms' => ($criteria?->text ?? []) + ($criteria && ! in_array($criteria->folder, ['', SearchCriteria::NO_FOLDER], true) ? ['fakelos' => $criteria->folder] : []),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'names' => User::pluck('name', 'id'),
            'folders' => \App\Models\Keepvalue::pluck('describe', 'fakelos'),
            'textFields' => SearchCriteria::TEXT_FIELDS,
            'mainText' => self::MAIN_TEXT,
            'emptyFields' => SearchCriteria::EMPTY_FIELDS,
        ]);
    }
}
