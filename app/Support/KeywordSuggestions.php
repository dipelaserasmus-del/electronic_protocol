<?php

namespace App\Support;

/**
 * R5d: keywords offered for an e-mail being registered, from the significant words of its subject (and the start of its
 * text). First the keywords already used in protocols whose words appear there (most used first, in their usual
 * spelling); then other significant words of the subject: long enough, not a common word, not a number.
 * Matching ignores case and accents (TagList::fold), and keywords already chosen are left out.
 *
 * R5e (owner, 2026-11-19): the attachments' file names count too ("ΙΑΤΡΙΚΗ ΓΝΩΜΑΤΕΥΣΗ.pdf"), after the subject; and a
 * word in capitals (no accents) takes the spelling it has in earlier subjects ("γνωμάτευση"), when there is one.
 */
final class KeywordSuggestions
{
    public const MAX = 8;

    /** Shortest word offered as a new keyword. */
    private const MIN_LETTERS = 5;

    /** How much of the e-mail's text is searched for known keywords (the subject always is). */
    private const TEXT_CHARS = 1500;

    /**
     * Common words that say nothing about the subject (folded: lower case, no accents, σ for ς): articles, pronouns,
     * prepositions, conjunctions, and the words of every official letter.
     */
    private const COMMON = [
        'αυτοσ', 'αυτη', 'αυτο', 'αυτοι', 'αυτεσ', 'αυτα', 'αυτου', 'αυτησ', 'αυτων', 'αυτον', 'αυτην', 'εκεινοσ',
        'οποιοσ', 'οποια', 'οποιο', 'οποιου', 'οποιασ', 'οποιων', 'οποιεσ', 'οποιοι', 'καθωσ', 'επειδη', 'ωστοσο',
        'σχετικα', 'σχετικο', 'σχετικη', 'σχετικεσ', 'σχετικων', 'σχετικοσ', 'σχετικου', 'σχετικησ', 'σχετικουσ',
        'σχετικα', 'μεταξυ', 'εναντι', 'εντοσ', 'εκτοσ', 'πριν', 'μετα', 'μεχρι', 'αλλων', 'αλλεσ', 'αλλου', 'αλλοι',
        'ολουσ', 'ολεσ', 'ολων', 'ολοι', 'καθε', 'πολυ', 'πολλα', 'πολλεσ', 'πολλων', 'μονο', 'επισησ', 'ακομη',
        'ακομα', 'οπωσ', 'οταν', 'οπου', 'ποιοσ', 'ποια', 'ποιο', 'εχουμε', 'εχετε', 'ειναι', 'ηταν', 'θελουμε',
        'παρακαλω', 'παρακαλουμε', 'παρακαλειστε', 'σασ', 'μασ', 'σημερα', 'αυριο', 'χθεσ', 'σχετ', 'θεμα', 'θεματα',
        'αποστολη', 'αποστολησ', 'διαβιβαση', 'διαβιβασησ', 'διαβιβαστικο', 'διαβιβαστικου', 'ενημερωση', 'ενημερωσησ',
        'ενημερωτικο', 'πληροφοριεσ', 'πληροφορια', 'επισυναπτεται', 'επισυναπτονται', 'συνημμενο', 'συνημμενα',
        'συνημμενων', 'εγγραφο', 'εγγραφου', 'εγγραφα', 'εγγραφων', 'εγγραφεσ', 'μηνυμα', 'μηνυματοσ', 'μηνυματα',
        'email', 'mail', 'fwd', 'fw', 're', 'reply', 'forward', 'προωθηση', 'απαντηση', 'αρχικο', 'σχολικο', 'σχολικου',
        'ετουσ', 'ετοσ', 'σχολικοσ', 'σχολικησ', 'σχολικεσ', 'σχολικων', 'σχολικα', 'about', 'with', 'from', 'this',
        'that', 'there', 'their', 'which', 'would', 'please', 'subject', 'regarding',
    ];

    /**
     * @param list<array{0: string, 1: int}> $known keywords in use, most used first (Suggestions::keywords)
     * @param list<string> $chosen keywords already in the field
     * @param list<string> $fileNames the attachments' names ("ΙΑΤΡΙΚΗ ΓΝΩΜΑΤΕΥΣΗ.pdf")
     * @param array<string, string> $spellings folded word => its usual spelling in subjects (Suggestions::spellings)
     * @return list<array{0: string, 1: bool}> [keyword, already in use]
     */
    public static function for(string $subject, string $text, array $known, array $chosen = [], array $fileNames = [], array $spellings = []): array
    {
        $taken = array_flip(array_map(TagList::fold(...), $chosen));
        $names = implode(' ', array_map(fn ($n) => preg_replace('/\.[\p{L}\p{N}]{1,5}$/u', '', (string) $n), $fileNames));
        $haystack = ' '.implode(' ', self::words($subject.' '.mb_substr($text, 0, self::TEXT_CHARS).' '.$names)).' ';
        $out = [];
        $add = function (string $keyword, bool $inUse) use (&$out, &$taken): void {
            $f = TagList::fold($keyword);
            if ($f !== '' && ! isset($taken[$f]) && count($out) < self::MAX) {
                $taken[$f] = true;
                $out[] = [$keyword, $inUse];
            }
        };

        // keywords already in use, whose words all appear together in the subject or the text
        foreach ($known as [$keyword]) {
            $words = self::words($keyword);
            if ($words !== [] && mb_strlen(implode('', $words)) >= 3 && str_contains($haystack, ' '.implode(' ', $words).' ')) {
                $add($keyword, true);
            }
        }
        // significant words of the subject, except those already in an offered keyword ("άδεια ασθενείας")
        $covered = [];
        foreach ($out as [$keyword]) {
            $covered += array_flip(self::words($keyword));
        }
        $spelling = [];
        foreach ($known as [$keyword]) {
            $spelling[TagList::fold($keyword)] ??= $keyword;
        }
        // the subject's words first, then the file names'
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $subject.' '.$names, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $f = TagList::fold($word);
            if (mb_strlen($f) < self::MIN_LETTERS || preg_match('/\p{N}/u', $f) || in_array($f, self::COMMON, true) || isset($covered[$f])) {
                continue;
            }
            $add($spelling[$f] ?? self::spelled($word, $spellings), isset($spelling[$f]));
        }

        return $out;
    }

    /** Folded words of a text. @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', TagList::fold($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * "Εκδρομή" → "εκδρομή"; "ΓΝΩΜΑΤΕΥΣΗ" → "γνωμάτευση" when earlier subjects spell it so (capitals have no accents to
     * keep), else "γνωματευση" (with final ς).
     */
    private static function spelled(string $word, array $spellings): string
    {
        $lower = mb_strtolower($word, 'UTF-8');
        $accented = preg_match('/\p{Mn}/u', \Normalizer::normalize($word, \Normalizer::FORM_D) ?: $word);
        if (! $accented) {
            $lower = $spellings[TagList::fold($word)] ?? $lower;
        }

        return preg_replace('/σ(?!\p{L})/u', 'ς', $lower);
    }
}
