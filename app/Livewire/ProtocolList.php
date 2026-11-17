<?php

namespace App\Livewire;

use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\NumberGaps;
use App\Services\ProtocolNumbering;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R2: the protocol list on the rebuilt layout. Filters (all / mine / to handle / to inform / done, for oneself or —
 * administrators and assigners — another user or everybody), sorting by column, sticky header, pills per row, the
 * missing-number banner (A02). The same addresses as before: /home/list, /home/list/{d|e|f|m}/{userId|a}.
 */
class ProtocolList extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    /**
     * '' all, 'm' mine (registered by me or assigned to me), 'a' registered by (the menu's per-user link
     * /home/list/a/{id}), 'd' to handle, 'e' to inform, 'f' handled
     */
    public const FILTERS = ['' => 'Όλα', 'm' => 'Δικά μου', 'a' => 'Καταχωρίσεις', 'd' => 'προς Διεκπεραίωση', 'e' => 'προς Ενημέρωση', 'f' => 'Διεκπεραιώθηκε'];

    /** Columns that can be sorted (all are columns of protocols, except the assignee) */
    public const SORTS = [
        'protocolnum' => 'Αριθμός', 'protocoldate' => 'Ημ/νία', 'fakelos' => 'Φάκελος', 'thema' => 'Θέμα',
        'in_num' => 'Αρ. εισερχ.', 'in_date' => 'Ημ/νία εισερχ.', 'in_arxi_ekdosis' => 'Αρχή έκδοσης', 'in_topos_ekdosis' => 'Τόπος',
        'in_paraliptis' => 'Παραλήπτης', 'out_to' => 'Απευθύνεται', 'out_date' => 'Ημ/νία εξερχ.', 'diekperaiosi' => 'Ανάθεση',
        'diekp_eos' => 'Έως', 'diekp_date' => 'Διεκπεραιώθηκε', 'sxetiko' => 'Σχετικοί', 'keywords' => 'Λέξεις κλειδιά',
        'in_perilipsi' => 'Περίληψη εισερχ.', 'out_perilipsi' => 'Περίληψη εξερχ.', 'paratiriseis' => 'Παρατηρήσεις',
    ];

    #[Url(as: 'filter', except: '')]
    public string $filter = '';

    /** 'me', 'all' or a user id (administrators and assigners only) */
    #[Url(as: 'user', except: 'me')]
    public string $who = 'me';

    #[Url(as: 'field', except: 'protocolnum')]
    public string $field = 'protocolnum';

    #[Url(except: 'desc')]
    public string $sort = 'desc';

    /** $routeFilter / $routeUser: from the old addresses /home/list/{filter}/{userId} ('a' = all users) */
    public function mount(?string $routeFilter = null, ?string $routeUser = null): void
    {
        // the address's path gives the defaults; a query string (changed on the page, then reloaded or bookmarked) wins
        if ($routeFilter !== null && ! request()->query->has('filter')) {
            $this->filter = $routeFilter;
        }
        if ($routeFilter !== null && ! request()->query->has('user')) {
            $this->who = $routeUser === null ? 'me' : ($routeUser === 'a' ? 'all' : $routeUser);
        }
        $this->normalise();
    }

    private function canSeeOthers(): bool
    {
        return in_array(ProtocolPolicy::role(Auth::user()), [ProtocolPolicy::ADMIN, ProtocolPolicy::ASSIGNER], true);
    }

    /** Anything the address or the browser sent that isn't allowed falls back to the default. */
    private function normalise(): void
    {
        if (! array_key_exists($this->filter, self::FILTERS)) {
            $this->filter = '';
        }
        if (! $this->canSeeOthers() || ! ($this->who === 'all' || $this->who === 'me' || (ctype_digit($this->who) && User::whereKey($this->who)->exists()))) {
            $this->who = 'me';
        }
        // R2b: under "Όλα", "me" already means everybody's protocols (as for every user); "all" is the same list
        if ($this->filter === '' && $this->who === 'all') {
            $this->who = 'me';
        }
        if (! array_key_exists($this->field, self::SORTS)) {
            $this->field = 'protocolnum';
        }
        if (! in_array($this->sort, ['asc', 'desc'], true)) {
            $this->sort = 'asc';
        }
    }

    public function updated(): void
    {
        $this->normalise();
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->updated();
    }

    public function sortBy(string $field): void
    {
        $this->sort = $this->field === $field && $this->sort === 'asc' ? 'desc' : 'asc';
        $this->field = $field;
        $this->updated();
    }

    /** The protocols of the current filter, sorted (newest year first, as before). */
    private function query()
    {
        $user = Auth::user();
        $q = Protocol::query()->with('attachments', 'assignments')->orderByDesc('etos');
        if ($this->field === 'diekperaiosi') {
            $q->orderBy(ProtocolAssignment::selectRaw('MIN(user_id)')->whereColumn('protocol_assignments.protocol_id', 'protocols.id')->where('type', 'd'), $this->sort);
        } else {
            $q->orderBy($this->field, $this->sort);
        }
        $q->orderByDesc('protocolnum');

        // whose list: one's own, another user's or everybody's (administrators and assigners)
        $for = $this->who === 'all' ? null : ($this->who === 'me' ? (int) $user->id : (int) $this->who);
        if (ProtocolPolicy::isLimited($user)) {
            $q->ownOrAssigned((int) $user->id);
        }
        match ($this->filter) {
            // R2b: "Όλα" with a chosen user = what that user registered or is assigned to ("me" = everything)
            '' => $this->who === 'me' ? null : $q->ownOrAssigned((int) $for),
            'm' => $for === null ? null : $q->ownOrAssigned($for),
            'a' => $for === null ? null : $q->where('user_id', $for),
            'd' => ($for === null ? $q->assignedFor('d') : $q->assignedTo($for, 'd'))->whereNull('diekp_date'),
            'e' => $for === null ? $q->assignedFor('e') : $q->assignedTo($for, 'e'),
            'f' => ($for === null ? $q->assignedFor('d') : $q->assignedTo($for, 'd'))->whereNotNull('diekp_date'),
        };
        return $q;
    }

    /** Bootstrap colour of the deadline (legacy rule: < 1 day danger, < 3 warning, < 6 info), none once handled. */
    public static function deadlineTone(Protocol $p): ?string
    {
        $eos = $p->dateOf('diekp_eos');
        if (! $eos || $p->diekp_date) {
            return null;
        }
        $days = Carbon::today()->diffInDays($eos, false);
        return match (true) {
            $days < 1 => 'danger',
            $days < 3 => 'warning',
            $days < 6 => 'info',
            default => null,
        };
    }

    private function title(): string
    {
        $base = self::FILTERS[$this->filter] === 'Όλα' ? 'Πρωτόκολλο' : self::FILTERS[$this->filter];
        if ($this->filter === '' && $this->who === 'me') {
            return $base;
        }
        if ($this->who === 'all') {
            return "Όλοι οι χρήστες · $base";
        }
        if ($this->who !== 'me') {
            return (User::find($this->who)?->name ?? '') . " · $base";
        }
        return $base;
    }

    /**
     * R2b: the user menu in two groups: users with open handling assignments (not yet marked "Διεκπεραιώθηκε"), with
     * their number, then everyone else.
     *
     * @return array{open: list<array{id: int, name: string, open: int}>, rest: list<array{id: int, name: string}>}
     */
    private function userGroups(): array
    {
        $open = ProtocolAssignment::query()
            ->join('protocols', 'protocols.id', '=', 'protocol_assignments.protocol_id')
            ->where('protocol_assignments.type', 'd')->whereNull('protocols.diekp_date')
            ->groupBy('protocol_assignments.user_id')
            ->selectRaw('protocol_assignments.user_id, COUNT(DISTINCT protocols.id) AS n')
            ->pluck('n', 'user_id');
        $groups = ['open' => [], 'rest' => []];
        foreach (User::orderBy('name')->get(['id', 'name']) as $u) {
            $n = (int) ($open[$u->id] ?? 0);
            $groups[$n > 0 ? 'open' : 'rest'][] = ['id' => $u->id, 'name' => $u->name, 'open' => $n];
        }
        return $groups;
    }

    public function render()
    {
        $user = Auth::user();
        $role = ProtocolPolicy::role($user);
        $seesGaps = in_array($role, [ProtocolPolicy::ADMIN, ProtocolPolicy::ASSIGNER, ProtocolPolicy::WRITER], true);

        return view('livewire.protocol-list', [
            'protocols' => $this->query()->paginate((int) (Config::getConfigValueOf('showRowsInPage') ?: 25)),
            'folders' => Keepvalue::pluck('describe', 'fakelos'),
            'userGroups' => $this->canSeeOthers() ? $this->userGroups() : [],
            'names' => User::pluck('name', 'id'),
            'numberGaps' => $seesGaps ? app(NumberGaps::class)->find() : [],
            'gapMonths' => (int) (Config::getConfigValueOf('gapCheckMonths') ?: NumberGaps::DEFAULT_MONTHS),
            'canCreate' => app(ProtocolNumbering::class)->userMayChooseNumber($user),
            'formYear' => (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year),
            'refreshSeconds' => (int) round(60 * (float) Config::getConfigValueOf('minutesRefreshInterval')),
            'diavgeiaUrl' => Config::getConfigValueOf('diavgeiaUrl'),
            'canSeeOthers' => $this->canSeeOthers(),
            'title' => $this->title(),
        ]);
    }
}
