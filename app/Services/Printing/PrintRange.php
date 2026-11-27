<?php

namespace App\Services\Printing;

use App\Models\Protocol;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * R7: which protocols a printout or the Excel export covers: year, numbers from/to, protocol dates from/to.
 * The parameters keep the old print form's names (etosForMany, aponum, eosnum, apoProtocolDate, eosProtocolDate),
 * so old bookmarks and the old POST /printed still work.
 *
 * Invalid values are not guessed: they are left out and listed in $ignored, and the printout says so (B07).
 * At most app.print_max_rows protocols (PRINT_MAX_ROWS in .env, 5000 if unset; R6m: no longer a setting) are printed or exported; the printout says when the
 * range had more (before R7 it was cut silently).
 */
final class PrintRange
{
    public const NUMBERS = ['etosForMany' => 'Έτος', 'aponum' => 'Από αριθμό', 'eosnum' => 'Έως αριθμό'];

    public const DATES = ['apoProtocolDate' => 'Από ημ/νία', 'eosProtocolDate' => 'Έως ημ/νία'];

    public ?int $year = null;
    public ?int $from = null;
    public ?int $to = null;
    public ?Carbon $fromDate = null;
    public ?Carbon $toDate = null;

    /** @var list<string> labels and values (escaped) of what was left out */
    public array $ignored = [];

    public static function fromInput(array $input): self
    {
        $r = new self();
        foreach (self::NUMBERS as $field => $label) {
            $v = trim((string) ($input[$field] ?? ''));
            if ($v === '') {
                continue;
            }
            if (! ctype_digit($v) || strlen($v) > 9) {
                $r->ignored[] = "$label «".e($v).'»';
                continue;
            }
            match ($field) { 'etosForMany' => $r->year = (int) $v, 'aponum' => $r->from = (int) $v, 'eosnum' => $r->to = (int) $v };
        }
        foreach (self::DATES as $field => $label) {
            $v = trim((string) ($input[$field] ?? ''));
            if ($v === '') {
                continue;
            }
            $date = DateInput::expand($v);   // as the date fields: 9/4/25, 09042025 …
            if (! $date) {
                $r->ignored[] = "$label «".e($v).'»';
                continue;
            }
            $date = Carbon::createFromFormat(DateInput::FORMAT, $date)->startOfDay();
            $field === 'apoProtocolDate' ? $r->fromDate = $date : $r->toDate = $date;
        }

        return $r;
    }

    /** Nothing chosen: the old page went back without a word; the new page asks for a year or a range */
    public function isEmpty(): bool
    {
        return $this->year === null && $this->from === null && $this->to === null && $this->fromDate === null && $this->toDate === null;
    }

    public function query(): Builder
    {
        $q = Protocol::query();
        if ($this->year !== null) {
            $q->where('etos', $this->year);
        }
        if ($this->from !== null) {
            $q->where('protocolnum', '>=', $this->from);
        }
        if ($this->to !== null) {
            $q->where('protocolnum', '<=', $this->to);
        }
        if ($this->fromDate !== null) {
            $q->where('protocoldate', '>=', $this->fromDate->toDateString());
        }
        if ($this->toDate !== null) {
            $q->where('protocoldate', '<=', $this->toDate->toDateString());
        }

        return $q->orderBy('etos')->orderBy('protocolnum');
    }

    public static function limit(): int
    {
        return max(1, (int) config('app.print_max_rows', 5000));
    }

    /** "έτους 2026", "έτους 2026, αριθμοί 10–20", "15/01/2026 – 31/01/2026" */
    public function describe(): string
    {
        $parts = [];
        if ($this->year !== null) {
            $parts[] = 'έτους '.$this->year;
        }
        if ($this->from !== null || $this->to !== null) {
            $parts[] = 'αριθμοί '.($this->from ?? 'αρχή').'–'.($this->to ?? 'τέλος');
        }
        if ($this->fromDate !== null || $this->toDate !== null) {
            $parts[] = 'ημερομηνίες '.($this->fromDate?->format('d/m/Y') ?? 'αρχή').' – '.($this->toDate?->format('d/m/Y') ?? 'σήμερα');
        }

        return implode(', ', $parts);
    }

    /** The parameters for a link to the printout */
    public function params(): array
    {
        return array_filter([
            'etosForMany' => $this->year, 'aponum' => $this->from, 'eosnum' => $this->to,
            'apoProtocolDate' => $this->fromDate?->format('d/m/Y'), 'eosProtocolDate' => $this->toDate?->format('d/m/Y'),
        ], fn ($v) => $v !== null);
    }
}
