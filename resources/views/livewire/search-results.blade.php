{{-- R3c: results of the top-bar search box. Rules: App\Livewire\SearchResults, App\Services\Search\QueryParser. --}}
<div class="search-page search-results-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Αποτελέσματα για <span class="text-break" id="results-query">«{{ $q }}»</span></h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill :href="$advanced" id="to-advanced">Σύνθετη αναζήτηση</x-pill>
            <x-pill :href="url('/home/list')">Λίστα</x-pill>
        </x-pill-actions>
    </div>

    @if ($ignored)
        <div class="alert alert-warning py-2" id="search-ignored" role="status">
            Δεν χρησιμοποιήθηκαν: {{ implode(' · ', $ignored) }}
        </div>
    @endif

    @if ($protocols === null)
        <div class="alert alert-info" id="search-empty">Γράψτε στο πεδίο αναζήτησης επάνω λέξεις ή έναν αριθμό και πατήστε Enter.</div>
    @else
        @include('search.results', ['protocols' => $protocols, 'terms' => $terms, 'words' => $criteria->words, 'names' => $names, 'folders' => $folders, 'showPending' => $criteria->pending])
    @endif

    <details class="card shadow-sm mt-3" id="search-help">
        <summary class="card-header fw-semibold">Λέξεις αναζήτησης</summary>
        <div class="card-body">@include('search.help')</div>
    </details>
</div>
