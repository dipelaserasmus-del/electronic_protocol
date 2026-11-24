{{-- N05: the user's notepad: a tab on the right edge of every page, a side panel (resources/modern/notes.js) --}}
<div class="notepad" x-data="notepad({ url: @js(url('/notes')) })" x-on:keydown.escape.window="open = false" id="notepad">
    <button type="button" class="notepad-tab btn btn-primary shadow" x-on:click="open = ! open" x-show="! open"
            aria-controls="notepad-panel" :aria-expanded="open" id="notepad-tab">
        <i class="bi bi-journal-text" aria-hidden="true"></i> <span>Σημειώσεις</span></button>
    <aside class="notepad-panel shadow-lg border-start" id="notepad-panel" x-show="open" x-cloak x-transition.opacity
           role="complementary" aria-label="Οι σημειώσεις μου">
        <div class="notepad-head d-flex align-items-center gap-2 px-3 py-2 border-bottom">
            <h2 class="h6 mb-0 me-auto"><i class="bi bi-journal-text" aria-hidden="true"></i> Οι σημειώσεις μου</h2>
            <div class="btn-group btn-group-sm" role="group" aria-label="Προβολή ή επεξεργασία">
                <button type="button" class="btn btn-outline-secondary" :class="mode === 'view' && 'active'" x-on:click="save(); mode = 'view'" id="notepad-view">Προβολή</button>
                <button type="button" class="btn btn-outline-secondary" :class="mode === 'edit' && 'active'" x-on:click="mode = 'edit'; $nextTick(() => $refs.text.focus())" id="notepad-edit">Επεξεργασία</button>
            </div>
            <button type="button" class="btn-close" x-on:click="open = false" aria-label="Κλείσιμο"></button>
        </div>
        <div class="notepad-conflict alert alert-warning m-2 py-2 small" x-show="conflict" x-cloak role="alert" id="notepad-conflict">
            Οι σημειώσεις αποθηκεύτηκαν μετά σε άλλη καρτέλα ή υπολογιστή.
            <div class="d-flex gap-2 mt-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="takeTheirs()" id="notepad-theirs">Φόρτωση των νεότερων</button>
                <button type="button" class="btn btn-sm btn-warning" x-on:click="keepMine()" id="notepad-mine">Κράτηση των δικών μου</button>
            </div>
        </div>
        <template x-if="mode === 'edit'">
            <div class="notepad-edit d-flex flex-column flex-grow-1 min-h-0">
                <div class="notepad-toolbar btn-toolbar gap-1 px-2 py-1 border-bottom" role="toolbar" aria-label="Μορφοποίηση">
                    <button type="button" class="btn btn-sm btn-light" title="Ενότητα" x-on:click="line('## ')"><i class="bi bi-type-h2"></i></button>
                    <button type="button" class="btn btn-sm btn-light" title="Έντονα" x-on:click="wrap('**')"><i class="bi bi-type-bold"></i></button>
                    <button type="button" class="btn btn-sm btn-light" title="Πλάγια" x-on:click="wrap('*')"><i class="bi bi-type-italic"></i></button>
                    <button type="button" class="btn btn-sm btn-light" title="Λίστα" x-on:click="line('- ')"><i class="bi bi-list-ul"></i></button>
                    <button type="button" class="btn btn-sm btn-light" title="Εκκρεμότητα" x-on:click="line('- [ ] ')" id="notepad-todo"><i class="bi bi-check2-square"></i></button>
                    <button type="button" class="btn btn-sm btn-light" title="Σύνδεσμος" x-on:click="wrap('[', '](https://)', 'σύνδεσμος')"><i class="bi bi-link-45deg"></i></button>
                </div>
                <textarea class="form-control border-0 rounded-0 flex-grow-1 notepad-text" x-ref="text" x-model="body" x-on:input="typed()"
                          maxlength="100000" aria-label="Σημειώσεις" spellcheck="true" id="notepad-text"
                          placeholder="# Ενότητα&#10;- [ ] κάτι να γίνει&#10;**έντονα**, *πλάγια*, [σύνδεσμος](https://…)"></textarea>
            </div>
        </template>
        <template x-if="mode === 'view'">
            <div class="notepad-view flex-grow-1 overflow-auto px-3 py-2" id="notepad-rendered" x-html="html"
                 x-on:change="tick($event)" x-on:toggle.capture="fold($event)"></div>
        </template>
        <div class="notepad-status small text-body-secondary px-3 py-1 border-top" x-text="status" id="notepad-status" aria-live="polite"></div>
    </aside>
</div>
