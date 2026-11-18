<?php

namespace App\Livewire;

use App\Models\Config;
use App\Services\Printing\PrintRange;
use Carbon\Carbon;
use Livewire\Component;

/**
 * R7: "Εκτύπωση πρωτοκόλλου" / "Εκτύπωση συνημμένων": choose the year, numbers and dates; the page says how many
 * protocols that is (and if more than the limit) before opening the printout (/printed, /printedAttachments) in a new
 * tab, or downloading the Excel file. The rules of the range: App\Services\Printing\PrintRange.
 */
class PrintPage extends Component
{
    /** book: the protocol book; attachments: protocols with attachments */
    public string $mode = 'book';

    public string $year = '';
    public string $from = '';
    public string $to = '';
    public string $fromDate = '';
    public string $toDate = '';

    public bool $mayPrintAttachments = false;

    public function mount(string $mode = 'book'): void
    {
        $this->mayPrintAttachments = auth()->user()?->role?->role !== \App\Support\Roles::READER;
        $this->mode = $mode === 'attachments' && $this->mayPrintAttachments ? 'attachments' : 'book';
        $this->year = (string) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'attachments' && $this->mayPrintAttachments ? 'attachments' : 'book';
    }

    private function range(): PrintRange
    {
        return PrintRange::fromInput([
            'etosForMany' => $this->year, 'aponum' => $this->from, 'eosnum' => $this->to,
            'apoProtocolDate' => $this->fromDate, 'eosProtocolDate' => $this->toDate,
        ]);
    }

    public function render()
    {
        $range = $this->range();
        $count = null;
        if (! $range->isEmpty()) {
            $q = $range->query();
            $count = $this->mode === 'attachments' ? $q->has('attachments')->count() : $q->count();
        }
        $target = url($this->mode === 'attachments' ? '/printedAttachments' : '/printed');

        return view('livewire.print-page', [
            'range' => $range,
            'count' => $count,
            'limit' => PrintRange::limit(),
            'printUrl' => $range->isEmpty() ? null : $target.'?'.http_build_query($range->params()),
            'xlsUrl' => $range->isEmpty() || $this->mode !== 'book' ? null : url('/printed').'?'.http_build_query($range->params() + ['target' => 'xls']),
        ]);
    }
}
