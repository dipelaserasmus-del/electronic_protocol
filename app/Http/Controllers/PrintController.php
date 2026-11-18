<?php

namespace App\Http\Controllers;

use App\Exports\ProtocolExport;
use App\Models\Protocol;
use App\Models\User;
use App\Services\Printing\PrintRange;
use App\Support\FileNames;
use Carbon\Carbon;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * R7: printing and export, on the new print layout (layouts/print).
 *  - /print, /printAttachments: the page that chooses what to print (App\Livewire\PrintPage)
 *  - /printed: the protocol book (13 numbered columns as before), or Excel with target=xls
 *  - /printedAttachments: the protocols with attachments, each file with its Διατήρηση and expiry
 *  - /receipt/{id}: the receipt of one protocol
 * Same guards as before (ProtocolController): the list limit (limitList) for printing, writers for the attachments
 * print and the receipt. /printed and /printedAttachments answer GET too (links from the new page; POST kept for old
 * bookmarks and forms).
 */
class PrintController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('limitList', only: ['page', 'attachmentsPage', 'book', 'attachments']),
            new Middleware('writer:home/list', only: ['attachmentsPage', 'attachments', 'receipt']),
        ];
    }

    public function page()
    {
        return view('print', ['mode' => 'book']);
    }

    public function attachmentsPage()
    {
        return view('print', ['mode' => 'attachments']);
    }

    public function book()
    {
        $range = PrintRange::fromInput(request()->all());
        if ($range->isEmpty()) {
            return redirect('/print');
        }
        $total = $range->query()->count();
        $protocols = $range->query()->with('assignments')->limit(PrintRange::limit())->get();
        $handlers = $this->handlerNames($protocols);

        if (request('target') === 'xls') {
            $name = FileNames::clean(trim((string) \App\Models\Config::getConfigValueOf('ipiresiasName').' - πρωτόκολλο '.$range->describe().' - '.Carbon::now()->format('Ymd-His')).'.xlsx');
            return Excel::download(new ProtocolExport('printedXls', ['protocols' => $protocols, 'range' => $range, 'handlers' => $handlers]), $name);
        }

        return view('printed', ['protocols' => $protocols, 'range' => $range, 'total' => $total, 'handlers' => $handlers, 'ignoredFilters' => $range->ignored]);
    }

    public function attachments()
    {
        $range = PrintRange::fromInput(request()->all());
        if ($range->isEmpty()) {
            return redirect('/printAttachments');
        }
        $query = $range->query()->has('attachments');
        $total = (clone $query)->count();
        $protocols = $query->with(['attachments' => fn ($q) => $q->orderBy('id')])->limit(PrintRange::limit())->get();

        return view('printedAttachments', ['protocols' => $protocols, 'range' => $range, 'total' => $total, 'ignoredFilters' => $range->ignored]);
    }

    public function receipt(Protocol $protocol)
    {
        abort_if(Gate::denies('view', $protocol), 404);   // S02
        return view('receipt', ['protocol' => $protocol]);
    }

    /** @return array<int, string> protocol id => names of the users assigned for διεκπεραίωση (also inactive ones) */
    private function handlerNames($protocols): array
    {
        $names = User::pluck('name', 'id');
        $out = [];
        foreach ($protocols as $p) {
            $out[$p->id] = $p->assignments->where('type', 'd')->map(fn ($a) => $names[$a->user_id] ?? '#'.$a->user_id)->implode(', ');
        }

        return $out;
    }
}
