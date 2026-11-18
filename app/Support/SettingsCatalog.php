<?php

namespace App\Support;

use App\Models\Config;

/**
 * R6c: every setting of "Ρυθμίσεις" in one place: its group, label, help, kind and validation. The page
 * (App\Livewire\SettingsPage) is drawn from this list and saves only these keys (the old page saved whatever was posted).
 *
 * Kinds: text, int, bool (stored "1" / "0"), select (options value => label), color, url.
 * Settings the rebuilt pages no longer read are not shown (searchField1–3, showUserInfo,
 * allowListValuesMatchingInput, wideListProtocol: see the migration 2026_11_19_000002).
 */
final class SettingsCatalog
{
    /** @return array<string, array{title: string, icon: string, settings: array<string, array>}> */
    public static function groups(): array
    {
        return [
            'office' => ['title' => 'Υπηρεσία και αρίθμηση', 'icon' => 'building', 'settings' => [
                'ipiresiasName' => ['label' => 'Όνομα υπηρεσίας / σχολείου', 'kind' => 'text', 'rules' => ['required', 'string', 'max:255'],
                    'help' => 'Φαίνεται κάτω από το όνομα της εφαρμογής, στις εκτυπώσεις και στις αποδείξεις.'],
                'yearInUse' => ['label' => 'Ενεργό έτος πρωτοκόλλησης', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'digits:4'],
                    'help' => 'Κενό: ο επόμενος αριθμός συνεχίζει διαχρονικά. Με έτος: η αρίθμηση ξεκινά από την αρχή κάθε έτος.'],
                'firstProtocolNum' => ['label' => 'Αριθμός πρωτοκόλλου για ξεκίνημα', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:0'],
                    'help' => 'Ο πρώτος αριθμός ενός άδειου βιβλίου, και η αρχή του ελέγχου κενών αριθμών (κενό ή 0 = 1).'],
                'gapCheckMonths' => ['label' => 'Έλεγχος κενών αριθμών: τελευταίοι μήνες', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:24'],
                    'help' => 'Η λίστα δείχνει τους αριθμούς που λείπουν, για πρωτόκολλα αυτού του διαστήματος (κενό = 6).'],
                'safeNewProtocolNum' => ['label' => 'Ασφαλής νέος αριθμός (ποτέ διπλός)', 'kind' => 'bool',
                    'help' => 'Ο αριθμός δίνεται τη στιγμή της αποθήκευσης, ώστε δύο χρήστες να μην πάρουν τον ίδιο.'],
                'protocolValidate' => ['label' => 'Έλεγχοι καταχώρισης', 'kind' => 'bool',
                    'help' => 'Ελέγχει ότι τα πεδία συμπληρώνονται μαζί (π.χ. αριθμός και ημερομηνία εισερχομένου) και το έτος. Κλειστό: ο Διαχειριστής επιλέγει αριθμό και ημερομηνία.'],
                'allowEmptyProtocol' => ['label' => 'Επιτρέπεται πρωτόκολλο χωρίς θέμα', 'kind' => 'bool',
                    'help' => 'Για να κρατηθεί ένας αριθμός που θα συμπληρωθεί αργότερα.'],
                'saveCycleSxetiko' => ['label' => 'Σχετικοί αριθμοί και στα δύο πρωτόκολλα', 'kind' => 'bool',
                    'help' => 'Ο σχετικός αριθμός γράφεται και στο άλλο πρωτόκολλο.'],
            ]],
            'rights' => ['title' => 'Δικαιώματα', 'icon' => 'shield-lock', 'settings' => [
                'allowWriterUpdateProtocol' => ['label' => 'Διόρθωση πρωτοκόλλου από Συγγραφείς', 'kind' => 'select',
                    'options' => ['0' => 'Κανένας', '1' => 'Μόνο όποιος το καταχώρισε', '2' => 'Όλοι οι Συγγραφείς'], 'rules' => ['required', 'in:0,1,2']],
                'allowWriterUpdateProtocolTimeInMinutes' => ['label' => 'Λεπτά για διόρθωση', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:0', 'max:525600'],
                    'help' => 'Πόσα λεπτά μετά την καταχώριση γίνεται διόρθωση (κενό ή 0 = χωρίς όριο).'],
                'allowWriterChangeDate' => ['label' => 'Ο Συγγραφέας αλλάζει την ημερομηνία πρωτοκόλλου', 'kind' => 'bool',
                    'help' => 'Συγγραφείς και Αναθέτοντες ορίζουν την ημερομηνία (π.χ. έγγραφο που παραλήφθηκε χθες). Αλλιώς μπαίνει η σημερινή.'],
                'limitProtocolAccessList' => ['label' => 'Περιορισμός λίστας', 'kind' => 'bool',
                    'help' => 'Συγγραφείς και Αναγνώστες βλέπουν μόνο τα πρωτόκολλα που έγραψαν ή τους ανατέθηκαν.'],
                'allowUserChangeKeepSelect' => ['label' => 'Ελεύθερη επιλογή χρόνου διατήρησης', 'kind' => 'bool',
                    'help' => 'Ο χρόνος διατήρησης των συνημμένων αλλάζει στο πρωτόκολλο. Αλλιώς ακολουθεί τον φάκελο.'],
            ]],
            'display' => ['title' => 'Εμφάνιση και λίστες', 'icon' => 'layout-text-window', 'settings' => [
                'showRowsInPage' => ['label' => 'Γραμμές ανά σελίδα', 'kind' => 'int', 'rules' => ['required', 'integer', 'min:5', 'max:1000']],
                'minutesRefreshInterval' => ['label' => 'Ανανέωση της λίστας κάθε … λεπτά', 'kind' => 'int', 'rules' => ['required', 'numeric', 'min:0', 'max:1440'],
                    'help' => '0 = χωρίς αυτόματη ανανέωση.'],
                'protocolArrowStep' => ['label' => 'Βήμα των κουμπιών Προηγούμενο / Επόμενο', 'kind' => 'int', 'rules' => ['required', 'integer', 'min:1', 'max:1000']],
                'titleColor' => ['label' => 'Χρώμα της πάνω μπάρας', 'kind' => 'color', 'rules' => ['nullable', 'string', 'regex:/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$/'],
                    'help' => 'Κενό = το μπλε της εφαρμογής.'],
                'maxRowsInFindPage' => ['label' => 'Μέγιστα αποτελέσματα αναζήτησης', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:100000']],
                'maxRowsInXlsExport' => ['label' => 'Μέγιστες γραμμές εξαγωγής σε Excel', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:1000000'],
                    'help' => 'Ανάλογα με τη μνήμη του διακομιστή (κενό = 5000).'],
            ]],
            'files' => ['title' => 'Συνημμένα', 'icon' => 'paperclip', 'settings' => [
                'putStamp' => ['label' => 'Αριθμός πρωτοκόλλου πάνω στα συνημμένα', 'kind' => 'bool',
                    'help' => 'Τα PDF, docx και odt παίρνουν σφραγίδα με τον αριθμό και την ημερομηνία.'],
                'diavgeiaUrl' => ['label' => 'Διεύθυνση Διαύγειας', 'kind' => 'url', 'rules' => ['nullable', 'url:https,http', 'max:255'],
                    'help' => 'Ένα ΑΔΑ γίνεται σύνδεσμος σε αυτή τη διεύθυνση.'],
                'trashRetentionDays' => ['label' => 'Κάδος: ημέρες μέχρι την οριστική διαγραφή', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:0', 'max:3650'],
                    'help' => 'Κενό = 30. 0 = μένουν μέχρι να αδειάσετε τον κάδο.'],
            ]],
            'email' => ['title' => 'E-mail', 'icon' => 'envelope', 'settings' => [
                'defaultImapEmail' => ['label' => 'Λογαριασμός εισερχομένων', 'kind' => 'select', 'options' => self::imapAccounts(),
                    'rules' => ['nullable', 'in:'.implode(',', array_keys(self::imapAccounts()))],
                    'help' => 'Κενό: χωρίς Εισερχόμενα. Οι λογαριασμοί ορίζονται στο config/imap.php.'],
                'allowedEmailUsers' => ['label' => 'Χρήστες που καταχωρίζουν e-mail', 'kind' => 'text', 'rules' => ['nullable', 'string', 'max:2000'],
                    'help' => 'Τα username χωρισμένα με κενό. Κενό: όλοι (οι Αναγνώστες δεν καταχωρίζουν).'],
                'daysToCheckEmailBack' => ['label' => 'E-mail των τελευταίων … ημερών', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:3650']],
                'emailNumFetch' => ['label' => 'E-mail ανά σελίδα', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:200']],
                'emailFetchOrderDesc' => ['label' => 'Σειρά e-mail', 'kind' => 'select', 'options' => ['1' => 'Νεότερα πρώτα', '0' => 'Παλαιότερα πρώτα'], 'rules' => ['required', 'in:0,1']],
                'alwaysShowFakelosInViewEmails' => ['label' => 'Ο Φάκελος χρειάζεται για κάθε e-mail', 'kind' => 'bool',
                    'help' => 'Αλλιώς μόνο για e-mail με συνημμένα.'],
                'alwaysSendReceitForEmails' => ['label' => 'Απόδειξη σε κάθε e-mail που καταχωρίζεται', 'kind' => 'bool'],
                'saveEmailAs' => ['label' => 'Το e-mail φυλάσσεται ως', 'kind' => 'select', 'options' => ['' => 'σελίδα (html)', 'eml' => 'αρχείο e-mail (eml)'], 'rules' => ['nullable', 'in:eml']],
                'sendEmailOnDiekperaiosiChange' => ['label' => 'E-mail σε όποιον ανατίθεται πρωτόκολλο', 'kind' => 'bool'],
            ]],
            'maintenance' => ['title' => 'Συντήρηση', 'icon' => 'tools', 'settings' => [
                'updatesAutoCheck' => ['label' => 'Έλεγχος για νέα έκδοση', 'kind' => 'bool',
                    'help' => 'Μετά τη σύνδεση ρωτά το GitHub αν υπάρχει νέα έκδοση.'],
                'mysqldumpPath' => ['label' => 'Διαδρομή του mysqldump', 'kind' => 'text', 'rules' => ['nullable', 'string', 'max:255', 'regex:/(^|[\\\\\\/])(mysqldump|mariadb-dump)(\.exe)?$/i'],
                    'help' => 'Κενό: αναζητείται αυτόματα. Γίνεται δεκτό μόνο αρχείο mysqldump ή mariadb-dump.'],
            ]],
        ];
    }

    /** @return array<string, array> key => definition (with 'group') */
    public static function all(): array
    {
        $all = [];
        foreach (self::groups() as $group => $g) {
            foreach ($g['settings'] as $key => $def) {
                $all[$key] = $def + ['group' => $group, 'rules' => ['nullable']];
            }
        }

        return $all;
    }

    /** @return array<string, string> the values as the page edits them */
    public static function values(): array
    {
        $stored = Config::getConfigValues();
        $values = [];
        foreach (self::all() as $key => $def) {
            $v = $stored[$key] ?? null;
            $values[$key] = $def['kind'] === 'bool' ? ($v ? '1' : '0') : (string) $v;
        }

        return $values;
    }

    /** @return array<string, array> validation rules for values.<key> */
    public static function rules(): array
    {
        $rules = [];
        foreach (self::all() as $key => $def) {
            $rules['values.'.$key] = $def['kind'] === 'bool' ? ['required', 'in:0,1'] : $def['rules'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return collect(self::all())->mapWithKeys(fn ($d, $k) => ['values.'.$k => '«'.$d['label'].'»'])->all();
    }

    /** @return array<string, string> '' => none, then the accounts of config/imap.php */
    public static function imapAccounts(): array
    {
        $accounts = ['' => '—'];
        foreach (array_keys((array) config('imap.accounts', [])) as $name) {
            $accounts[(string) $name] = (string) $name;
        }

        return $accounts;
    }
}
