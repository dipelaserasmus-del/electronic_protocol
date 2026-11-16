<?php

namespace App\Livewire;

use App\Models\Attachment;
use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\ProtocolNumbering;
use App\Services\Protocols\ProtocolAccess;
use App\Services\Protocols\ProtocolWriter;
use App\Services\Protocols\SaveResult;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * R1b: the protocol form (new, edit, copy as new) on the rebuilt layout. Everything it saves goes through
 * ProtocolWriter (R1a), which applies the same rules again: this screen only shows them.
 *
 * Owner's requests built in: the number shows at once whether it is free or "Δεσμευμένος"; TAB from the number goes
 * to the date; date fields with ◀ ▶, "Σήμερα" and a calendar; an existing subject or incoming number is shown as a
 * notice next to the field instead of the old YES/NO dialog; actions as pills.
 */
class ProtocolForm extends Component
{
    use WithFileUploads;

    public const FIELDS = ['etos', 'protocolnum', 'protocoldate', 'fakelos', 'thema', 'in_num', 'in_date', 'in_topos_ekdosis',
        'in_arxi_ekdosis', 'in_paraliptis', 'in_perilipsi', 'diekp_eos', 'diekp_date', 'sxetiko', 'out_to', 'out_date',
        'out_perilipsi', 'keywords', 'paratiriseis', 'keep', 'reply_to_email'];

    public ?int $protocolId = null;
    public ?int $copyOf = null;

    /** @var array<string, string> the form's fields, as typed */
    public array $form = [];

    /** @var list<string> user ids for διεκπεραίωση / ενημέρωση */
    public array $handle = [];
    public array $inform = [];

    public bool $replacePlaceholder = false;
    public bool $noStamp = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> new files (R1c adds drag & drop) */
    public $uploads = [];
    public string $newAda = '';

    public function mount(?Protocol $protocol = null, bool $copy = false, ?int $prefillNum = null): void
    {
        $protocol ??= new Protocol();
        if ($protocol->exists) {
            abort_if(Auth::user()->cannot('view', $protocol), 404);
        }
        $this->form = array_fill_keys(self::FIELDS, '');
        foreach (self::FIELDS as $f) {
            if (in_array($f, ProtocolWriter::DATES, true)) {
                $this->form[$f] = (string) $protocol->dateOf($f)?->format(DateInput::FORMAT);
            } elseif ($protocol->exists && $protocol->getAttribute($f) !== null && ! in_array($f, ['keep', 'reply_to_email'], true)) {
                $this->form[$f] = (string) $protocol->getAttribute($f);
            }
        }
        foreach ($protocol->exists ? $protocol->assignments : [] as $a) {
            $a->type === 'd' ? $this->handle[] = (string) $a->user_id : $this->inform[] = (string) $a->user_id;
        }
        $this->form['keep'] = (string) ($protocol->exists ? $protocol->attachments()->value('keep') : '') ?: $this->keepFor($this->form['fakelos']);

        if ($protocol->exists && ! $copy) {
            $this->protocolId = $protocol->id;
            return;
        }
        // new, or a copy: number and year are given on save; the copy keeps its date when the user may set it (R1a)
        $access = ProtocolAccess::for(Auth::user(), new Protocol());
        $this->copyOf = $copy && $protocol->exists ? $protocol->id : null;
        $this->form['etos'] = (string) $this->activeYear();
        $this->form['protocolnum'] = $prefillNum && $access->number ? (string) $prefillNum : '';
        if (! $this->copyOf || ! $access->date || $this->form['protocoldate'] === '') {
            $this->form['protocoldate'] = Carbon::today()->format(DateInput::FORMAT);
        }
        $this->form['diekp_date'] = '';
        if (! $access->canAssign) {
            $this->handle = $this->inform = [];
            $this->form['diekp_eos'] = '';
        }
    }

    private function protocol(): Protocol
    {
        return $this->protocolId ? Protocol::with('assignments', 'attachments')->findOrFail($this->protocolId) : new Protocol();
    }

    private function activeYear(): int
    {
        return (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
    }

    private function keepFor(?string $folder): string
    {
        $k = $folder ? Keepvalue::whereFakelos($folder)->first() : null;
        return (string) ($k?->keep ?: $k?->keep_alt ?: '');
    }

    /** A different folder suggests its retention ("Διατήρηση"), as the old form did. */
    public function updatedFormFakelos(string $value): void
    {
        $this->form['keep'] = $this->keepFor($value);
    }

    public function updatedFormProtocolnum(): void
    {
        $this->replacePlaceholder = false;
    }

    public function save(ProtocolWriter $writer)
    {
        $input = $this->form + [
            'diekperaiosi' => array_merge(array_map(fn ($id) => "d$id", $this->handle), array_map(fn ($id) => "e$id", $this->inform)),
            'replace_placeholder' => $this->replacePlaceholder,
            'explicityNoStamp' => $this->noStamp,
            'protocolnum_proposed' => '',   // any number typed in the new form is the user's choice (B03)
        ];
        $files = array_map(fn ($f) => ['file' => $f, 'ada' => null], array_values((array) $this->uploads));
        if (trim($this->newAda) !== '') {
            $files[] = ['file' => null, 'ada' => $this->newAda];
        }
        try {
            $result = $this->protocolId
                ? $writer->update(Auth::user(), $this->protocol(), $input, $files)
                : $writer->create(Auth::user(), $input, $files);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return null;
        }
        return $this->done($result, $this->protocolId ? 'Οι αλλαγές αποθηκεύτηκαν.' : "Καταχωρίστηκε με αριθμό {$result->protocol->protocolnum}/{$result->protocol->etos}.");
    }

    public function assignOnly(ProtocolWriter $writer)
    {
        $result = $writer->assign(Auth::user(), $this->protocol(),
            array_merge(array_map(fn ($id) => "d$id", $this->handle), array_map(fn ($id) => "e$id", $this->inform)), $this->form['diekp_eos']);
        return $this->done($result, 'Η ανάθεση αποθηκεύτηκε.');
    }

    public function markDone(ProtocolWriter $writer)
    {
        $protocol = $this->protocol();
        $writer->markDone(Auth::user(), $protocol, $this->form['diekp_date'] ?: Carbon::today()->format(DateInput::FORMAT));
        session()->flash('notification', ['message' => 'Καταχωρίστηκε η ημερομηνία διεκπεραίωσης.', 'alert-type' => 'success']);
        return $this->redirect(url("/home/{$protocol->id}"));
    }

    public function removeAttachment(int $attachmentId, ProtocolWriter $writer): void
    {
        $attachment = Attachment::where('protocol_id', $this->protocolId)->findOrFail($attachmentId);
        try {
            $writer->removeAttachment(Auth::user(), $attachment);
            $this->dispatch('toast', message: "Το «{$attachment->name}» μεταφέρθηκε στον κάδο.", type: 'success');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function removeUpload(int $index): void
    {
        $uploads = array_values((array) $this->uploads);
        unset($uploads[$index]);
        $this->uploads = array_values($uploads);
    }

    public function deleteProtocol(ProtocolWriter $writer)
    {
        $protocol = $this->protocol();
        $label = "{$protocol->protocolnum}/{$protocol->etos}";
        $writer->delete(Auth::user(), $protocol);
        session()->flash('notification', ['message' => "Διαγράφηκε το πρωτόκολλο $label.", 'alert-type' => 'success']);
        return $this->redirect(url('/home/list'));
    }

    private function done(SaveResult $result, string $headline)
    {
        $lines = '';
        foreach ($result->messages as $line) {
            $lines .= '<li>' . e($line) . '</li>';
        }
        foreach ($result->warnings as $line) {
            $lines .= '<li><strong>' . e($line) . '</strong></li>';
        }
        session()->flash('notification', [
            'message' => e($headline) . ($lines ? "<ul>$lines</ul>" : ''),
            'alert-type' => $result->warnings ? 'warning' : 'success',
        ]);
        return $this->redirect(url("/home/{$result->protocol->id}"));
    }

    /**
     * The number field's state: null (not editable), ['auto'], ['free'], ['taken', Protocol], ['placeholder', Protocol]
     * or ['invalid']. "Δεσμευμένος" = used by another protocol (owner's term).
     */
    private function numberStatus(ProtocolAccess $access): ?array
    {
        if (! $access->number) {
            return null;
        }
        $num = trim($this->form['protocolnum']);
        if ($num === '') {
            return $this->protocolId ? ['invalid'] : ['auto'];
        }
        if (! ctype_digit($num) || (int) $num < 1 || ! ctype_digit(trim($this->form['etos']))) {
            return ['invalid'];
        }
        $other = Protocol::where('etos', (int) $this->form['etos'])->where('protocolnum', (int) $num)
            ->when($this->protocolId, fn ($q) => $q->where('id', '!=', $this->protocolId))->first();
        if (! $other) {
            return ['free'];
        }
        return [ProtocolWriter::isPlaceholder($other) ? 'placeholder' : 'taken', $other];
    }

    /** Other protocols the user may see with the same subject / incoming number + date (were YES/NO dialogs). */
    private function similar(string $field): array
    {
        $query = Protocol::query()->when($this->protocolId, fn ($q) => $q->where('id', '!=', $this->protocolId));
        if ($field === 'thema') {
            $thema = trim($this->form['thema']);
            if ($thema === '' || ProtocolWriter::isPlaceholder(new Protocol(['thema' => $thema]))) {
                return [];
            }
            $query->where('thema', $thema);
        } else {
            $date = DateInput::expand((string) $this->form['in_date']);
            if (trim($this->form['in_num']) === '' || ! $date) {
                return [];
            }
            $query->where('in_num', trim($this->form['in_num']))
                ->where('in_date', Carbon::createFromFormat('!' . DateInput::FORMAT, $date)->format('Y-m-d'));
        }
        if (ProtocolPolicy::isLimited(Auth::user())) {
            $query->ownOrAssigned((int) Auth::id());
        }
        return $query->orderByDesc('etos')->orderByDesc('protocolnum')->take(5)->get(['id', 'protocolnum', 'etos'])->all();
    }

    public function render()
    {
        $protocol = $this->protocol();
        $user = Auth::user();
        $access = ProtocolAccess::for($user, $protocol);
        $isReader = ProtocolPolicy::role($user) === ProtocolPolicy::READER;

        return view('livewire.protocol-form', [
            'protocol' => $protocol,
            'access' => $access,
            'title' => match (true) {
                $this->copyOf !== null => 'Αντιγραφή ως νέο πρωτόκολλο',
                ! $protocol->exists => 'Νέο πρωτόκολλο',
                $access->canSave => 'Επεξεργασία πρωτοκόλλου',
                default => 'Πρωτόκολλο',
            },
            'numberStatus' => $this->numberStatus($access),
            'nextNumber' => $protocol->exists ? null : app(ProtocolNumbering::class)->next((int) ($this->form['etos'] ?: $this->activeYear())),
            'sameSubject' => $this->similar('thema'),
            'sameIncoming' => $this->similar('in_num'),
            'folders' => Keepvalue::orderByRaw('SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-3)+0<>0 DESC, SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-(3))+0, `fakelos`')->get(['fakelos', 'describe']),
            'keepYears' => Keepvalue::whereNotNull('keep')->distinct()->orderBy('keep')->pluck('keep'),
            'keepWords' => Keepvalue::whereNotNull('keep_alt')->distinct()->orderBy('keep_alt')->pluck('keep_alt'),
            'users' => User::get_writers_and_admins(),
            'creator' => $protocol->exists ? User::find($protocol->user_id) : null,
            'attachments' => $protocol->exists ? $protocol->attachments : collect(),
            'canComplete' => $protocol->exists && ! $access->canSave && $access->doneDate && $user->can('complete', $protocol),
            'isReader' => $isReader,
            'putStamp' => (bool) Config::getConfigValueOf('putStamp'),
            'diavgeiaUrl' => Config::getConfigValueOf('diavgeiaUrl'),
        ]);
    }
}
