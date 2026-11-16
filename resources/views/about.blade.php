@extends('layouts.modern')
{{-- N03: first page on the new layout. Same text as before; only the markup changed (Bootstrap 5). --}}
@section('title', 'Πληροφορίες')
@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <h1 class="h2 text-center mb-4">Πληροφορίες</h1>

            <div class="card mb-4">
                <div class="card-header text-center h5 bg-warning-subtle">Ομάδα δημιουργίας του Ηλ.Πρωτοκόλλου</div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h2 class="h5 text-center">
                                Γεώργιος Θεοδώρου
                                <a href="mailto:g.theodoroy@gmail.com?subject=Ηλ.Πρωτόκολλο" title="Αποστολή email">
                                    <img src="{{ asset('images/email.png') }}" height="15" alt="Αποστολή email">
                                </a>
                            </h2>
                            <p class="text-justify">
                                Καθηγητής Φυσικής Αγωγής.<br>Υπηρετεί στην Δ/θμια Εκπση Αχαΐας.
                                Επιμελήθηκε το σχεδιασμό και την υλοποίηση του project.
                                Συντηρεί, ενημερώνει και βελτιώνει το project.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h2 class="h5 text-center">Παναγιώτης Ζώτος</h2>
                            <p class="text-justify">
                                Διοικητικος με ειδικότητα Πληροφορικής.<br>Υπηρετεί στην Δ/θμια Εκπση Αχαΐας.
                                Με την μεγάλη του διοικητική εμπειρία βοήθησε τα μάλα στον σχεδιασμό του project.
                                Επιμελήθηκε τους Φακέλους Φ. καθώς και το χρόνο διατήρησης των αρχείων.
                            </p>
                        </div>
                        <div class="col-12">
                            Πολύτιμη ήταν η συμβολή μελών της κοινότητας
                            <a href="http://alkisg.mysch.gr/steki/index.php?topic=6958.0" target="_blank" rel="noopener">Το Στέκι των Πληροφορικών</a>
                            που με τις ιδέες και τις προτάσεις τους ραφινάρισαν το project...
                        </div>
                    </div>

                    <h2 class="h5 text-center bg-warning-subtle py-1 mt-4">Εύσημα...</h2>
                    <p>
                        Το Ηλ. Πρωτόκολλο είναι διαδικτυακή εφαρμογή. Είναι γραμμένη σε γλώσσα PHP και χρησιμοποιεί
                        τα μοντέρνα framework <a href="https://laravel.com/" target="_blank" rel="noopener"><strong>Laravel</strong></a>,
                        <a href="https://getbootstrap.com/" target="_blank" rel="noopener"><strong>Bootstrap</strong></a>,
                        τη βιβλιοθήκη javascript <a href="https://jquery.com/" target="_blank" rel="noopener"><strong>jQuery</strong></a>, ...
                    </p>
                    <h2 class="h5 text-center bg-warning-subtle py-1">Αποποίηση ευθυνών</h2>
                    <p class="mb-0">
                        Παρέχεται "ώς έχει" σύμφωνα με τη "Γενική Άδεια Δημόσιας Χρήσης GNU".
                        Τακτικά backup θα προστατέψουν τα δεδομένα σας. Η ομάδα δημιουργίας δεν φέρει καμιά ευθυνη
                        για τυχόν προβλήματα δημιουργηθούν από τη μή σώφρονα χρήση του.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header text-center h5 bg-danger-subtle">Υποστήριξη Ηλ.Πρωτοκόλλου</div>
                <div class="card-body row align-items-center">
                    <div class="col-md-8">
                        Το Ηλ.Πρωτόκολλο είναι και θα είναι <b>δωρεάν</b>.
                        Αν σας εξυπηρετεί, σας τέρπει και επιθυμείτε μπορείτε να κάνετε μια μικρή δωρεά...
                    </div>
                    <div class="col-md-4 text-center mt-3 mt-md-0">
                        <form action="https://www.paypal.com/donate" method="post" target="_top">
                            <input type="hidden" name="hosted_button_id" value="TW3TKMTMDLHK2">
                            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif"
                                   name="submit" title="PayPal - The safer, easier way to pay online!"
                                   alt="Donate with PayPal button">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
