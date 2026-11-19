<?php

namespace App\Livewire;

use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\User;
use App\Services\Search\ProtocolSearch;
use App\Services\Search\QueryParser;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R3c: the results page of the search box in the top bar (/search?q=…), separate from the advanced screen (owner's
 * decision). The text is read by QueryParser (R3b); every year unless έτος: is given; newest first; only the
 * protocols the user may see. "Σύνθετη αναζήτηση" opens the advanced screen with the same search filled in.
 */
class SearchResults extends Component
{
    use WithPagination;
    use \App\Livewire\Concerns\PagesWithinRange;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $q = '';

    public function render()
    {
        $parser = new QueryParser();
        $criteria = $parser->parse($this->q);
        $protocols = null;
        if (! $criteria->isEmpty()) {
            $perPage = (int) (Config::getConfigValueOf('maxRowsInFindPage') ?: 30);
            $protocols = $this->pageOf(app(ProtocolSearch::class)->query($criteria, Auth::user()), $perPage);
        }

        return view('livewire.search-results', [
            'criteria' => $criteria,
            'ignored' => $parser->ignored,
            'protocols' => $protocols,
            'advanced' => url('/find') . '?' . http_build_query(SearchPage::paramsFor($criteria)),
            'terms' => $criteria->text + (in_array($criteria->folder, ['', \App\Services\Search\SearchCriteria::NO_FOLDER], true) ? [] : ['fakelos' => $criteria->folder]),
            'names' => User::pluck('name', 'id'),
            'folders' => Keepvalue::pluck('describe', 'fakelos'),
        ]);
    }
}
