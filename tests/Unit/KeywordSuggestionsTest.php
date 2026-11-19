<?php

namespace Tests\Unit;

use App\Support\KeywordSuggestions;
use PHPUnit\Framework\TestCase;

/** R5d: keywords offered for an e-mail, from its subject (and the start of its text). Invented subjects. */
class KeywordSuggestionsTest extends TestCase
{
    public function test_known_keywords_first_then_significant_words_of_the_subject(): void
    {
        $known = [['Εκδρομή', 12], ['άδεια ασθενείας', 5], ['Διαύγεια', 3], ['ΠΥΣΠΕ', 2]];

        $out = KeywordSuggestions::for('ΑΝΑΡΡΩΤΙΚΗ ΑΔΕΙΑ ΑΣΘΕΝΕΙΑΣ εκπαιδευτικού για εκδρομή', '', $known);

        $this->assertSame([['Εκδρομή', true], ['άδεια ασθενείας', true], ['αναρρωτικη', false], ['εκπαιδευτικού', false]], $out,
            'known ones in their usual spelling (case/accents ignored, two-word keywords too, their words not again); '
            .'then new words in lower case (a word in capitals has no accents to keep)');
    }

    public function test_common_words_numbers_short_words_and_chosen_ones_are_left_out(): void
    {
        $out = KeywordSuggestions::for('Fwd: Διαβιβαστικό εγγράφων σχετικά με 2026-27 το Πρόγραμμα Σπουδών', 'κείμενο', [], ['σπουδών']);

        $this->assertSame([['πρόγραμμα', false]], $out);
    }

    public function test_known_keywords_are_found_in_the_text_too_but_new_words_only_in_the_subject(): void
    {
        $out = KeywordSuggestions::for('Αίτηση', "Καλημέρα,\nσας στέλνουμε την αίτηση για τη Διαύγεια και τις μετακινήσεις.", [['Διαύγεια', 3], ['ΑΙΤΗΣΗ', 1]]);

        $this->assertSame([['Διαύγεια', true], ['ΑΙΤΗΣΗ', true]], $out);
    }

    public function test_at_most_max(): void
    {
        $this->assertCount(KeywordSuggestions::MAX, KeywordSuggestions::for(
            'αλφαβητο βιβλίο γεωγραφία δεντρο ελαιόλαδο ζωγραφική ηλιοβασίλεμα θάλασσα ιστορία καλοκαίρι λεμόνι', '', []));
    }

    /**
     * R5e (owner, 2026-11-19): the attachments' file names count too, after the subject; their extensions do not; a word
     * in capitals takes its spelling from earlier subjects when there is one.
     */
    public function test_words_of_the_file_names_after_the_subject(): void
    {
        $names = ['ΑΙΤΗΣΗ-ΔΙΑΒΙΒΑΣΤΙΚΟ.pdf', 'ΙΑΤΡΙΚΗ ΓΝΩΜΑΤΕΥΣΗ.pdf', 'ΥΠΕΡΗΧΟΓΡΑΦΗΜΑ.bmp', 'scan_0001.jpeg'];
        $spellings = ['γνωματευση' => 'γνωμάτευση', 'αιτηση' => 'αίτηση'];

        $out = KeywordSuggestions::for('Αναρρωτική άδεια', '', [], [], $names, $spellings);

        $this->assertSame([['αναρρωτική', false], ['άδεια', false], ['αίτηση', false], ['ιατρικη', false], ['γνωμάτευση', false], ['υπερηχογραφημα', false]], $out,
            'the subject\'s words first; then the names\' (διαβιβαστικό is a common word; pdf, bmp, jpeg and scan are not kept)');
    }

    public function test_keywords_in_use_are_found_in_the_file_names(): void
    {
        $out = KeywordSuggestions::for('Fwd: έγγραφα', '', [['Δικαιολογητικά', 9], ['Γνωμάτευση', 4]], [], ['ΙΑΤΡΙΚΗ ΓΝΩΜΑΤΕΥΣΗ.pdf']);

        $this->assertSame([['Γνωμάτευση', true], ['ιατρικη', false]], $out, 'in its usual spelling, first');
    }
}
