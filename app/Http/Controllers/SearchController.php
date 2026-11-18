<?php

namespace App\Http\Controllers;

use App\Services\Search\ProtocolSearch;
use App\Services\Search\QueryParser;
use App\Support\Highlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * R3c: the search box in the top bar. /search?q=… is the results page (Livewire SearchResults); /search/suggest?q=…
 * gives the few protocols shown while typing. Both search only what the user may see (ProtocolSearch).
 */
class SearchController extends Controller
{
    /** protocols shown while typing */
    public const SUGGEST = 6;

    public function results()
    {
        return view('protocols.results');
    }

    public function suggest(): JsonResponse
    {
        $text = mb_substr(trim((string) request('q')), 0, 200);
        $criteria = (new QueryParser())->parse($text);
        if (mb_strlen($text) < 2 || $criteria->isEmpty()) {
            return response()->json(['items' => [], 'more' => false]);
        }
        $found = app(ProtocolSearch::class)->query($criteria, Auth::user())->take(self::SUGGEST + 1)->get();
        $mark = array_merge($criteria->words, array_values($criteria->text));

        return response()->json([
            'items' => $found->take(self::SUGGEST)->map(fn ($p) => [
                'url' => url('/home/' . $p->id),
                'number' => $p->protocolnum . '/' . $p->etos,
                'date' => $p->dateOf('protocoldate')?->format('d/m/Y'),
                'html' => Highlight::html(mb_strimwidth((string) $p->thema, 0, 140, '…'), $mark),   // escaped, marks only
                'from' => $p->in_arxi_ekdosis ?: ($p->out_to ? '→ ' . $p->out_to : null),
            ])->values(),
            'more' => $found->count() > self::SUGGEST,
        ]);
    }
}
