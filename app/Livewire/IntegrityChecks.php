<?php

namespace App\Livewire;

use App\Services\Integrity\Checks;
use App\Services\ProtocolNumbering;
use App\Support\DateInput;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A01: «Έλεγχοι» (/checks): every rule of App\Services\Integrity\Checks over a date range of protocol dates, the last
 * 30 days unless the user picks another (in the address, so a check can be bookmarked). Only reads: each finding links
 * to its protocol, where the user corrects what needs correcting.
 */
class IntegrityChecks extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Checks::allowed(auth()->user()), 403);
        [$from, $to] = Checks::defaultRange();
        $this->from = $this->from !== '' ? $this->from : $from->format(DateInput::FORMAT);
        $this->to = $this->to !== '' ? $this->to : $to->format(DateInput::FORMAT);
    }

    /** Quick ranges: 'days' (the default 30 days), 'year' (1 January to today) */
    public function range(string $which): void
    {
        $today = CarbonImmutable::today();
        [$from, $to] = $which === 'year' ? [$today->startOfYear(), $today] : Checks::defaultRange($today);
        [$this->from, $this->to] = [$from->format(DateInput::FORMAT), $to->format(DateInput::FORMAT)];
        $this->resetErrorBag();
    }

    /** The form's «Έλεγχος»: render() checks the typed range */
    public function check(): void
    {
        $this->resetErrorBag();
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} the typed range, or nulls with an error on the field */
    private function typedRange(): array
    {
        $date = function (string $field) {
            $value = DateInput::expand(trim($this->{$field}));
            if (! $value) {
                $this->addError($field, 'Μη έγκυρη ημερομηνία (π.χ. 9, 9/4 ή 09/04/2026).');

                return null;
            }
            $this->{$field} = $value;

            return CarbonImmutable::createFromFormat('!'.DateInput::FORMAT, $value);
        };
        [$from, $to] = [$date('from'), $date('to')];
        if ($from && $to && $from->greaterThan($to)) {
            $this->addError('to', 'Η τελική ημερομηνία είναι πριν από την αρχική.');

            return [null, null];
        }
        if ($from && $to && $from->diffInDays($to) > Checks::MAX_DAYS) {
            $this->addError('to', 'Έως τρία χρόνια τη φορά.');

            return [null, null];
        }

        return [$from, $to];
    }

    public function render(Checks $checks, ProtocolNumbering $numbering)
    {
        $user = auth()->user();
        abort_unless(Checks::allowed($user), 403);
        [$from, $to] = $this->typedRange();
        $results = $from && $to ? $checks->run($user, $from, $to) : null;

        return view('livewire.integrity-checks', [
            'results' => $results,
            'total' => $results ? array_sum(array_map(fn ($r) => count($r['findings']), $results)) : 0,
            'canCreate' => $numbering->userMayChooseNumber($user),
            'formYear' => (int) (\App\Models\Config::getConfigValueOf('yearInUse') ?: CarbonImmutable::now()->year),
        ]);
    }
}
