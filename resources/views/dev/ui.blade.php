@extends('layouts.modern')
{{-- N04: the shared components in one page, for the browser tests and for trying them. Not available in production. --}}
@section('title', 'Δοκιμή εξαρτημάτων')
@section('content')
    <h1 class="h3 mb-3">Δοκιμή κοινών εξαρτημάτων <small class="text-body-secondary fs-6">(μόνο σε ανάπτυξη)</small></h1>
    @if (request('submitted'))
        <x-notice type="success" id="submitted">Η φόρμα υποβλήθηκε (ημερομηνία: {{ request('the_date') }}).</x-notice>
    @endif

    <section class="card mb-3"><div class="card-body">
        <h2 class="h5">Ημερομηνία</h2>
        <form method="GET" action="{{ url('/dev/ui') }}" id="date-form" class="row g-3"
              data-confirm="Να υποβληθεί η φόρμα;" data-confirm-yes="Υποβολή">
            <input type="hidden" name="submitted" value="1">
            <x-date-input name="the_date" label="Ημερομηνία" class="col-md-6" id="the-date" />
            <x-date-input name="prefilled" label="Συμπληρωμένη" value="09/03/2026" class="col-md-6" id="prefilled-date" />
            <x-date-input name="locked" label="Μόνο ανάγνωση" value="01/02/2026" :readonly="true" class="col-md-6" id="locked-date" />
            <div class="col-12">
                <x-pill-actions id="form-pills">
                    <x-pill type="submit" variant="primary">Υποβολή</x-pill>
                    <x-pill type="reset">Καθαρισμός</x-pill>
                </x-pill-actions>
            </div>
        </form>
    </div></section>

    <section class="card mb-3"><div class="card-body">
        <h2 class="h5">Ενέργειες</h2>
        <x-pill-actions id="actions">
            <x-pill variant="primary" onclick="appToast('Αποθηκεύτηκε', 'success')">Αποθήκευση</x-pill>
            <x-pill :href="url('/dev/ui#receipt')">Απόδειξη</x-pill>
            <x-pill onclick="appToast('Στάλθηκε με email', 'info')">Αποστολή email</x-pill>
            <x-pill onclick="appToast('Η αποστολή απέτυχε', 'error')">Δοκιμή σφάλματος</x-pill>
            <x-pill :href="url('/dev/ui#copy')">Αντιγραφή</x-pill>
            <x-pill :href="url('/dev/ui#print')">Εκτύπωση</x-pill>
            <x-pill variant="danger" :href="url('/dev/ui#deleted')" data-confirm="Να διαγραφεί το πρωτόκολλο 123/2026;" data-confirm-yes="Διαγραφή">Διαγραφή</x-pill>
        </x-pill-actions>
    </div></section>

    <section class="card mb-3"><div class="card-body">
        <h2 class="h5">Αρχεία</h2>
        <ul class="list-unstyled mb-0" id="files">
            <li><x-file-badge name="Απόφαση.pdf" :size="1258291" /> Απόφαση.pdf</li>
            <li><x-file-badge name="αίτηση.docx" :size="36960" /> αίτηση.docx</li>
            <li><x-file-badge name="πίνακας.xlsx" :size="950" /> πίνακας.xlsx</li>
            <li><x-file-badge name="email_20260305_101500.eml" :size="21099835" /> email_20260305_101500.eml</li>
            <li><x-file-badge name="" mime="application/pdf" /> (χωρίς όνομα)</li>
        </ul>
    </div></section>

    <section class="card mb-3"><div class="card-body">
        <h2 class="h5">Ειδοποιήσεις</h2>
        <x-notice>Το θέμα υπάρχει ήδη στο πρωτόκολλο <a href="#">123/2026</a>.</x-notice>
        <x-notice type="warning">Ο αριθμός 124 είναι ήδη δεσμευμένος.</x-notice>
        <x-notice type="error">Μη έγκυρη ημερομηνία.</x-notice>
    </div></section>
@endsection
