@extends('errors.layout')
@section('title', 'Σφάλμα')
@section('content')
    <h1>Κάτι πήγε στραβά</h1>
    <p>Η ενέργεια δεν ολοκληρώθηκε λόγω απρόσμενου σφάλματος. Ό,τι είχε ήδη αποθηκευτεί πριν από το σφάλμα
        παραμένει αποθηκευμένο.</p>
    <p>Κωδικός σφάλματος: <span class="code" id="error-id">{{ $errorId ?? '—' }}</span></p>
    <p class="muted">Αν το πρόβλημα επαναλαμβάνεται, δώστε τον κωδικό αυτό στον Διαχειριστή:
        βρίσκει την πλήρη περιγραφή στο αρχείο καταγραφής (<code>storage/logs</code>) αναζητώντας τον κωδικό.</p>
    @if (!empty($details))
        <details open id="error-details">
            <summary>Τεχνικές λεπτομέρειες (εμφανίζονται μόνο σε Διαχειριστές)</summary>
            <pre>{{ $details['type'] }}
{{ $details['message'] }}

Σημείο:   {{ $details['location'] }}
Αίτημα:   {{ $details['request'] }}
@foreach ($details['app_frames'] as $frame)
  ↳ {{ $frame }}
@endforeach</pre>
        </details>
    @endif
@endsection
