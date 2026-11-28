<?php

namespace App\Livewire;

use App\Models\AuditEntry;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\Audit\AuditPresenter;
use App\Support\DateInput;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * X02: the audit log for administrators (Διαχείριση → Ιστορικό ενεργειών, /history; a protocol's «Ιστορικό» opens it
 * with the protocol filled in). Newest first, PER_PAGE a page; filters by user, action, dates and protocol, all in the
 * address; «Εξαγωγή CSV» downloads what is filtered. Only reads: the log cannot be changed (X01).
 */
class AuditHistory extends Component
{
    use WithPagination;

    public const PER_PAGE = 50;

    /** user id, NOBODY (no user: failed sign-ins, the automatic clean-ups) or '' (all) */
    public const NOBODY = 'none';

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $user = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** «125/2026» */
    #[Url]
    public string $protocol = '';

    public static function allowed(?User $user): bool
    {
        return $user !== null && ProtocolPolicy::role($user) === ProtocolPolicy::ADMIN;
    }

    public function boot(): void
    {
        abort_unless(self::allowed(Auth::user()), 403);
    }

    /** «Αναζήτηση»: the typed filters checked, from the first page */
    public function apply(): void
    {
        $this->resetErrorBag();
        foreach (['from', 'to'] as $field) {
            $value = trim($this->{$field});
            if ($value !== '' && ! ($this->{$field} = (string) DateInput::expand($value))) {
                $this->{$field} = $value;
                $this->addError($field, 'Μη έγκυρη ημερομηνία (π.χ. 9, 9/4 ή 09/04/2026).');
            }
        }
        $this->protocol = trim($this->protocol);
        if ($this->protocol !== '' && self::number($this->protocol) === null) {
            $this->addError('protocol', 'Γράψτε αριθμό/έτος, π.χ. 125/2026.');
        }
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset('user', 'action', 'from', 'to', 'protocol');
        $this->resetErrorBag();
        $this->resetPage();
    }

    /** «Εξαγωγή CSV»: every filtered entry (all pages), newest first, for Excel (UTF-8 with BOM, «;») */
    public function export()
    {
        $query = $this->query();
        AuditPresenter::fresh();
        $name = 'istoriko-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ημερομηνία', 'Ώρα', 'Χρήστης', 'IP', 'Ενέργεια', 'Αφορά', 'Πρωτόκολλο (id)', 'Αλλαγές', 'Σημειώσεις'], ';', '"', '');
            $query->chunk(500, function ($entries) use ($out) {
                foreach ($entries as $e) {
                    fputcsv($out, [
                        $e->created_at->format('d/m/Y'), $e->created_at->format('H:i:s'), (string) $e->username, (string) $e->ip,
                        AuditPresenter::action($e->action), AuditPresenter::subject($e), (string) $e->protocol_id,
                        implode("\n", array_map(fn ($c) => "{$c[0]}: {$c[1]} → {$c[2]}", AuditPresenter::changes($e, short: false))),
                        AuditPresenter::notes($e),
                    ], ';', '"', '');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The filtered entries, newest first (dates and numbers that are not valid are left out of the filter) */
    private function query(): Builder
    {
        $q = AuditEntry::query()->orderByDesc('id');
        if ($this->user === self::NOBODY) {
            $q->whereNull('user_id');
        } elseif (ctype_digit($this->user)) {
            $q->where('user_id', (int) $this->user);
        }
        if (isset(AuditPresenter::ACTIONS[$this->action])) {
            $q->where('action', $this->action);
        }
        foreach (['from' => '>=', 'to' => '<'] as $field => $op) {
            $date = DateInput::expand(trim($this->{$field}));
            if ($date) {
                $day = CarbonImmutable::createFromFormat('!'.DateInput::FORMAT, $date);
                $q->where('created_at', $op, $field === 'to' ? $day->addDay() : $day);
            }
        }
        if (($number = self::number($this->protocol)) !== null) {
            // the protocol's entries (its attachments and assignments too), and those written when it had this number
            // and is gone now (purged) or numbered again (restored with a new number)
            $ids = Protocol::where('protocolnum', $number[0])->where('etos', $number[1])->pluck('id');
            $ref = $number[0].'/'.$number[1];
            $q->where(fn ($w) => $w->whereIn('protocol_id', $ids)->orWhere(fn ($r) => $r->where('entity_type', 'protocol')->where('context->ref', $ref)));
        }

        return $q;
    }

    /** «125/2026» → [125, 2026] */
    private static function number(string $text): ?array
    {
        return preg_match('~^\s*(\d{1,9})\s*/\s*(\d{4})\s*$~', $text, $m) ? [(int) $m[1], (int) $m[2]] : null;
    }

    public function render()
    {
        $number = self::number($this->protocol);
        AuditPresenter::fresh();

        return view('livewire.audit-history', [
            'entries' => $this->query()->paginate(self::PER_PAGE),
            'users' => User::orderBy('name')->get(['id', 'name', 'username']),
            'protocolId' => $number ? Protocol::where('protocolnum', $number[0])->where('etos', $number[1])->value('id') : null,
            'keepDays' => \App\Services\Audit\AuditLog::keepDays(),
        ]);
    }
}
