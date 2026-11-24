<?php

namespace App\Livewire;

use App\Services\Logs\LogReader;
use App\Support\Highlight;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * R6e: the application's log for administrators (Διαχείριση → Αρχεία καταγραφής), like a console: one row per entry
 * (time, coloured level, message), a click shows its details (the stack trace marked up); level filters, search with
 * the matches marked, a day at a time (‹ date ›, calendar) and pages within a day, refreshed every 10 s if asked.
 * Read by App\Services\Logs\LogReader (only the application's own files); everything shown is escaped.
 */
class LogViewer extends Component
{
    public const PER_PAGE = 100;

    #[Url]
    public string $day = '';

    /** @var list<string> levels shown ([] = all) */
    #[Url]
    public array $levels = [];

    #[Url]
    public string $q = '';

    #[Url]
    public int $page = 1;

    public bool $live = false;

    /** @var list<string> entries shown open */
    public array $open = [];

    public function boot(): void
    {
        abort_unless(Auth::user()?->role?->role === 'Διαχειριστής', 403);
    }

    public function mount(LogReader $reader): void
    {
        $days = $reader->days();
        if (! in_array($this->day, $days, true)) {
            $this->day = $days[0] ?? now()->format('Y-m-d');
        }
        $this->levels = array_values(array_intersect($this->levels, LogReader::LEVELS));
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['levels', 'q', 'day'], true) || str_starts_with($name, 'levels.')) {
            $this->page = 1;
            $this->open = [];
        }
    }

    /** the previous (-1) / next (+1) day that has entries */
    public function step(int $by, LogReader $reader): void
    {
        $days = $reader->days();   // newest first
        $at = array_search($this->day, $days, true);
        $target = $at === false ? ($days[0] ?? null) : ($days[$at - $by] ?? null);
        if ($target !== null) {
            [$this->day, $this->page, $this->open] = [$target, 1, []];
        }
    }

    public function toggle(string $id): void
    {
        $this->open = in_array($id, $this->open, true) ? array_values(array_diff($this->open, [$id])) : [...$this->open, $id];
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->open = [];
    }

    /** a stack trace / context: escaped, the search marked, "#n file(line): call" lines marked up */
    public static function details(string $text, string $q): string
    {
        $out = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^(#\d+) (.+?)\((\d+)\): (.*)$/', $line, $m)) {
                $out[] = '<span class="lt-n">' . e($m[1]) . '</span> <span class="lt-file">' . Highlight::html($m[2], $q) . '</span>(<span class="lt-line">'
                    . e($m[3]) . '</span>): <span class="lt-call">' . Highlight::html($m[4], $q) . '</span>';
            } elseif (preg_match('/^(#\d+) (.*)$/', $line, $m)) {
                $out[] = '<span class="lt-n">' . e($m[1]) . '</span> <span class="lt-call">' . Highlight::html($m[2], $q) . '</span>';
            } else {
                $out[] = Highlight::html($line, $q);
            }
        }
        return implode("\n", $out);
    }

    public function render(LogReader $reader)
    {
        $all = $reader->day($this->day);
        $counts = array_count_values(array_column($all, 'level'));
        $q = trim($this->q);
        $fold = fn (string $s) => \App\Support\TagList::fold($s);
        $entries = array_values(array_filter($all, function ($e) use ($q, $fold) {
            if ($this->levels && ! in_array($e['level'], $this->levels, true)) {
                return false;
            }
            return $q === '' || str_contains($fold($e['message'] . "\n" . $e['details']), $fold($q));
        }));
        $pages = max(1, (int) ceil(count($entries) / self::PER_PAGE));
        $this->page = min(max(1, $this->page), $pages);
        $days = $reader->days();

        return view('livewire.log-viewer', [
            'entries' => array_slice($entries, ($this->page - 1) * self::PER_PAGE, self::PER_PAGE),
            'found' => count($entries),
            'total' => count($all),
            'counts' => $counts,
            'pages' => $pages,
            'days' => $days,
            'truncated' => $reader->truncated,
            'files' => $reader->files(),
            'allLevels' => LogReader::LEVELS,
        ]);
    }
}
