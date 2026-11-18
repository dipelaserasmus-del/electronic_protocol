{{--
    R3c: the search box in the top bar. Enter opens the results page (/search?q=…); while typing, a few matching
    protocols are shown (/search/suggest) with ↑ ↓ Enter, and the search words (φάκελος: …) are offered as they are
    typed. "/" anywhere on the page puts the cursor in the box. Script: resources/modern/search.js.
--}}
<form action="{{ url('/search') }}" method="GET" role="search" class="app-search position-relative" id="app-search"
      x-data="searchBox(@js(url('/search/suggest')))" x-on:click.outside="close()" x-on:submit="submitted()">
    <label for="app-search-q" class="visually-hidden">Αναζήτηση πρωτοκόλλων</label>
    {{-- R3d: the search button and the advanced search (it was the menu's "Αναζήτηση") are part of the box --}}
    <div class="input-group input-group-sm">
        <input type="search" name="q" id="app-search-q" class="form-control" autocomplete="off"
               value="{{ request()->is('search') ? request('q') : '' }}" placeholder="Αναζήτηση…  ( / )" maxlength="500"
               role="combobox" aria-autocomplete="list" aria-controls="app-search-list" :aria-expanded="open"
               x-ref="input" x-on:input="typed()" x-on:keydown="key($event)" x-on:focus="if (items.length || hints.length) open = true">
        <button type="submit" class="btn btn-light" title="Αναζήτηση" aria-label="Αναζήτηση"><i class="bi bi-search" aria-hidden="true"></i></button>
        <a href="{{ url('/find') }}" class="btn btn-light border-start" id="app-search-advanced" title="Σύνθετη αναζήτηση"
           aria-label="Σύνθετη αναζήτηση"><i class="bi bi-sliders2" aria-hidden="true"></i></a>
    </div>
    <div class="list-group shadow app-search-list" id="app-search-list" role="listbox" x-show="open" x-cloak>
        <template x-for="(h, n) in hints" :key="'h' + h[0]">
            <button type="button" class="list-group-item list-group-item-action py-1 small search-hint" role="option"
                    :class="{ active: n === active }" x-on:mousedown.prevent="useHint(h)" x-on:mousemove="active = n">
                <code x-text="h[0]"></code> <span class="text-body-secondary" x-text="h[1]"></span>
            </button>
        </template>
        <template x-for="(it, n) in items" :key="it.url">
            <a :href="it.url" class="list-group-item list-group-item-action py-1 search-suggestion" role="option"
               :class="{ active: n + hints.length === active }" x-on:mousemove="active = n + hints.length">
                <span class="fw-semibold" x-text="it.number"></span> <small class="opacity-75" x-text="it.date"></small>
                <div class="small text-truncate" x-html="it.html"></div>
                <div class="small opacity-75 text-truncate" x-show="it.from" x-text="it.from"></div>
            </a>
        </template>
        <div class="list-group-item small text-body-secondary py-1" x-show="query.length > 1 && ! loading && ! items.length && ! hints.length">Κανένα πρωτόκολλο ακόμη· Enter για αναζήτηση.</div>
        <a :href="allUrl()" class="list-group-item list-group-item-action py-1 small fw-semibold search-all" x-show="query.length > 0"
           :class="{ active: active === hints.length + items.length }">
            Όλα τα αποτελέσματα για «<span x-text="query"></span>»<span x-show="more"> …</span>
        </a>
    </div>
</form>
