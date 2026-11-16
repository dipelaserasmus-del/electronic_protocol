<?php

namespace App\Support;

/**
 * R1c (owner): an attachment's file name as the protocol's subject, when the subject is left empty.
 * "Πρόσκληση_σε  ημερίδα.pdf" → "Πρόσκληση σε ημερίδα": extension removed, underscores and repeated spaces become one
 * space. Names that say nothing (only digits/symbols, e.g. a scanner's "00012.pdf") give null.
 */
final class SubjectFromFile
{
    public static function guess(?string $fileName): ?string
    {
        $name = TextInput::clean($fileName);
        if ($name === null) {
            return null;
        }
        if (preg_match('/^\.[A-Za-z0-9]{1,4}$/', $name)) {
            return null;   // only an extension
        }
        $name = preg_replace('/(?<=.)\.[A-Za-z0-9]{1,4}$/u', '', $name) ?? $name;   // .pdf .docx .jpeg, not ".final"
        $name = trim(preg_replace('/[\s_]+/u', ' ', $name) ?? '', " -.");
        if (! preg_match('/\p{L}/u', $name)) {
            return null;
        }
        return mb_substr($name, 0, 255);
    }
}
