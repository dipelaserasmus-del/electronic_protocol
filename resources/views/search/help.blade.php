{{-- R3b/R3c: the search words (App\Services\Search\QueryParser), as a short list --}}
<dl class="row small mb-0 search-help">
    <dt class="col-sm-4"><code>λέξη "φράση"</code></dt><dd class="col-sm-8">σε οποιοδήποτε πεδίο (χωρίς τόνους/κεφαλαία)</dd>
    {{-- R3f (owner, 2026-11-20) --}}
    <dt class="col-sm-4"><code>=120</code> <code>εισ:=120</code></dt><dd class="col-sm-8">ολόκληρη λέξη ή αριθμός: μόνο 120 (και 120/3), όχι 234120 ή 1205</dd>
    <dt class="col-sm-4"><code>125</code> <code>125/2026</code></dt><dd class="col-sm-8">αριθμός πρωτοκόλλου</dd>
    <dt class="col-sm-4"><code>αρ:120-130</code></dt><dd class="col-sm-8">αριθμός ή από–έως</dd>
    <dt class="col-sm-4"><code>έτος:2026</code></dt><dd class="col-sm-8">έτος (χωρίς αυτό: όλα τα έτη)</dd>
    <dt class="col-sm-4"><code>ημ:1/1/2026..31/3/2026</code></dt><dd class="col-sm-8">ημερομηνία ή διάστημα (<code>ημεισ:</code> <code>ημεξ:</code> εισερχ./εξερχ.)</dd>
    {{-- R3i --}}
    <dt class="col-sm-4"><code>πότε:σήμερα</code> <code>πότε:7μέρες</code></dt><dd class="col-sm-8"><code>σήμερα</code> <code>χθες</code> <code>εβδομάδα</code> (από Δευτέρα) <code>7μέρες</code> (τελευταίες Ν, με σήμερα) <code>μήνας</code> <code>φέτος</code> <code>πέρσι</code>· και στα <code>ημ:</code> <code>ημεισ:</code> <code>ημεξ:</code></dd>
    <dt class="col-sm-4"><code>φάκελος:φ12</code></dt><dd class="col-sm-8">φάκελος (κωδικός ή λέξη περιγραφής· <code>φάκελος:κανένας</code>)</dd>
    <dt class="col-sm-4"><code>από:</code> <code>προς:</code> <code>θέμα:</code></dt><dd class="col-sm-8">αρχή έκδοσης, απευθύνεται, θέμα</dd>
    <dt class="col-sm-4"><code>εισ:</code> <code>σχετ:</code> <code>λέξη:</code></dt><dd class="col-sm-8">αρ. εισερχομένου, σχετικοί αριθμοί, λέξεις κλειδιά</dd>
    <dt class="col-sm-4"><code>συνημμένο:</code> <code>αδα:</code></dt><dd class="col-sm-8">όνομα συνημμένου, ΑΔΑ</dd>
    <dt class="col-sm-4"><code>ανάθεση:Μαρία</code></dt><dd class="col-sm-8">ανάθεση διεκπεραίωσης (<code>ανάθεση:κανένας</code>)</dd>
    <dt class="col-sm-4"><code>χρήστης:Μαρία</code></dt><dd class="col-sm-8">ποιος καταχώρισε το πρωτόκολλο (μέρος του ονόματος)</dd>
    <dt class="col-sm-4"><code>κενό:φάκελος</code></dt><dd class="col-sm-8">το πεδίο είναι κενό</dd>
    <dt class="col-sm-4"><code>είναι:εκκρεμές</code></dt><dd class="col-sm-8">χωρίς φάκελο ή «ΕΚΚΡΕΜΟΤΗΤΑ» στο θέμα</dd>
</dl>
<div class="small text-body-secondary mt-1">Τιμή με κενά σε εισαγωγικά: <code>από:"Δήμος Πατρέων"</code>.</div>
