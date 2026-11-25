<?php

namespace App\Support;

use App\Models\Config;

/**
 * R6c: every setting of "Ρυθμίσεις" in one place: its group, label, help, kind and validation. The page
 * (App\Livewire\SettingsPage) is drawn from this list and saves only these keys (the old page saved whatever was posted).
 *
 * Kinds: text, int, bool (stored "1" / "0"), select (options value => label), color, url.
 * Settings the rebuilt pages no longer read are not shown (searchField1–3, showUserInfo,
 * allowListValuesMatchingInput: see the migration 2026_11_19_000002; wideListProtocol is still read by the old
 * layout). R6d (owner, 2026-11-19) also hides updatesAutoCheck (nothing checks for updates since B07),
 * protocolArrowStep (no screen moves by more than 1) and safeNewProtocolNum (the number is always given on
 * save: ProtocolWriter); their stored values stay untouched.
 */
final class SettingsCatalog
{
    /**
     * Each setting: label, kind, rules, 'help' (the line under it) and 'more' (the fuller explanation of its ⓘ:
     * what it does, the default, an example, related settings). 'box' puts settings that work together in one
     * frame; the group's 'boxes' give its title and the line that says how they combine (R6d).
     *
     * R6f (owner, 2026-11-25): grouped as GitHub's settings: SECTIONS says which section (and heading) each setting is in;
     * this list keeps each setting's text and rules (its own groups are only how it was written).
     */
    private static function definitions(): array
    {
        return [
            'office' => ['title' => 'Υπηρεσία και αρίθμηση', 'icon' => 'building',
                'boxes' => ['numbering' => ['title' => 'Αρχή αρίθμησης και κενοί αριθμοί',
                    'help' => 'Οι δύο ρυθμίσεις ισχύουν μαζί: ο έλεγχος κενών ξεκινά από τον μεγαλύτερο από τους δύο αριθμούς, '
                        .'τον «Αριθμό για ξεκίνημα» (μόνο για το τρέχον έτος) και τον πρώτο αριθμό των τελευταίων μηνών, '
                        .'και φτάνει μέχρι τον τελευταίο αριθμό του έτους.']],
                'settings' => [
                'ipiresiasName' => ['label' => 'Όνομα υπηρεσίας / σχολείου', 'kind' => 'text', 'rules' => ['required', 'string', 'max:255'],
                    'help' => 'Φαίνεται στην πάνω μπάρα, στις εκτυπώσεις, στις αποδείξεις και στη σφραγίδα των συνημμένων.',
                    'more' => 'Γράφεται κάτω από το όνομα της εφαρμογής, στην κεφαλίδα των εκτυπώσεων και του αρχείου Excel (και στο όνομά του), '
                        .'στις αποδείξεις καταχώρισης, στα e-mail ανάθεσης και στη σφραγίδα των PDF, docx και odt.'],
                'yearInUse' => ['label' => 'Ενεργό έτος πρωτοκόλλησης', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'digits:4'],
                    'help' => 'Το έτος των νέων πρωτοκόλλων. Όταν το αλλάξετε σε νέο έτος, η αρίθμηση ξεκινά από το 1.',
                    'more' => 'Με έτος (π.χ. 2026): κάθε νέο πρωτόκολλο παίρνει αυτό το έτος και τον επόμενο αριθμό του. Τον Ιανουάριο '
                        .'γράψτε το νέο έτος: η αρίθμηση ξεκινά ξανά από το 1. Όσο είναι ανοιχτοί οι «Έλεγχοι καταχώρισης», νέο '
                        .'πρωτόκολλο δεν αποθηκεύεται αν εδώ δεν είναι το τρέχον έτος. Κενό: το έτος είναι το τρέχον και η αρίθμηση '
                        .'συνεχίζει χωρίς να ξεκινά ποτέ από την αρχή. Είναι και το προεπιλεγμένο έτος της Αναζήτησης και της Εκτύπωσης.'],
                'firstProtocolNum' => ['label' => 'Αριθμός πρωτοκόλλου για ξεκίνημα', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:0'], 'box' => 'numbering',
                    'help' => 'Ο πρώτος αριθμός ενός βιβλίου χωρίς πρωτόκολλα. Για το τρέχον έτος, ο έλεγχος κενών δεν ξεκινά πριν από αυτόν (κενό ή 0 = 1).',
                    'more' => 'Δύο χρήσεις. 1) Όταν δεν υπάρχει κανένα πρωτόκολλο, το πρώτο παίρνει αυτόν τον αριθμό. 2) Στο τρέχον έτος, '
                        .'αριθμοί πριν από αυτόν δεν αναφέρονται ως κενοί, π.χ. όταν η αρίθμηση της χρονιάς συνεχίζει από άλλο βιβλίο. '
                        .'Παράδειγμα: με 1500, και πρώτο αριθμό των τελευταίων μηνών το 1200, ελέγχονται οι αριθμοί από το 1500. '
                        .'Με 800, ελέγχονται από το 1200: εδώ δεν αλλάζει τίποτα. Τα προηγούμενα έτη δεν επηρεάζονται.'],
                'gapCheckMonths' => ['label' => 'Έλεγχος κενών αριθμών: τελευταίοι μήνες', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:24'], 'box' => 'numbering',
                    'help' => 'Η λίστα δείχνει τους αριθμούς που λείπουν από τα πρωτόκολλα αυτών των μηνών (κενό = 6).',
                    'more' => 'Ο έλεγχος ξεκινά από τον πρώτο αριθμό με ημερομηνία μέσα σε αυτούς τους μήνες (ή από τον «Αριθμό για '
                        .'ξεκίνημα», αν είναι μεγαλύτερος) και φτάνει στον τελευταίο αριθμό του έτους. Αν το διάστημα πιάνει και την '
                        .'προηγούμενη χρονιά, ελέγχεται κι εκείνη. Τα κενά φαίνονται σε Διαχειριστές, Αναθέτοντες και Συγγραφείς, ως '
                        .'πράσινες γραμμές «ΚΕΝΟ» στη λίστα.'],
                'protocolValidate' => ['label' => 'Έλεγχοι καταχώρισης', 'kind' => 'bool',
                    'help' => 'Ελέγχει τα πεδία πριν από την αποθήκευση. Κλειστό: ο Διαχειριστής επιλέγει αριθμό, έτος και ημερομηνία.',
                    'more' => 'Ανοιχτό: το θέμα χρειάζεται· τα στοιχεία του εισερχομένου (αριθμός, ημερομηνία, τόπος, αρχή έκδοσης, '
                        .'παραλήπτης) και του εξερχομένου συμπληρώνονται μαζί· το έτος του νέου πρωτοκόλλου είναι το τρέχον. Κλειστό: '
                        .'κανένας από αυτούς τους ελέγχους· ο Διαχειριστής γράφει αριθμό, έτος και ημερομηνία, διαγράφει από τη φόρμα και '
                        .'καταχωρίζει έναν κενό αριθμό από τη λίστα.'],
                'allowEmptyProtocol' => ['label' => 'Επιτρέπεται πρωτόκολλο χωρίς θέμα', 'kind' => 'bool',
                    'help' => 'Κράτηση αριθμού: ένα πρωτόκολλο χωρίς κανένα στοιχείο, που συμπληρώνεται αργότερα.',
                    'more' => 'Μετρά μόνο όταν οι «Έλεγχοι καταχώρισης» είναι ανοιχτοί (κλειστοί: το θέμα δεν χρειάζεται ποτέ). Γίνεται '
                        .'δεκτό μόνο εντελώς άδειο πρωτόκολλο, χωρίς θέμα, άλλα στοιχεία ή ανάθεση. Ένα πρωτόκολλο χωρίς θέμα το '
                        .'συμπληρώνει κάθε Συγγραφέας και Αναθέτων.'],
                'saveCycleSxetiko' => ['label' => 'Σχετικοί αριθμοί και στα δύο πρωτόκολλα', 'kind' => 'bool',
                    'help' => 'Ο σχετικός αριθμός γράφεται και στο άλλο πρωτόκολλο.',
                    'more' => 'Όταν στους «Σχετικούς αριθμούς» γράφετε π.χ. 125/2026 και αυτό το πρωτόκολλο υπάρχει, το 125/2026 παίρνει '
                        .'στους δικούς του σχετικούς τον αριθμό που αποθηκεύετε. Δεν γράφεται αν εκεί το πεδίο θα ξεπερνούσε τους 255 χαρακτήρες.'],
            ]],
            'rights' => ['title' => 'Δικαιώματα', 'icon' => 'shield-lock', 'settings' => [
                'allowWriterUpdateProtocol' => ['label' => 'Διόρθωση πρωτοκόλλου από Συγγραφείς και Αναθέτοντες', 'kind' => 'select',
                    'options' => ['0' => 'Κανένας', '1' => 'Μόνο όποιος το καταχώρισε', '2' => 'Όλοι οι Συγγραφείς και Αναθέτοντες'], 'rules' => ['required', 'in:0,1,2'],
                    'help' => 'Ποιοι διορθώνουν ένα πρωτόκολλο μετά την καταχώριση. Ο Διαχειριστής διορθώνει πάντα.',
                    'more' => 'Οι Αναγνώστες δεν διορθώνουν ποτέ. Εξαιρέσεις: ένα πρωτόκολλο χωρίς θέμα το συμπληρώνουν όλοι· όποιος '
                        .'έχει την ανάθεση για Διεκπεραίωση συμπληρώνει το εξερχόμενο και την ημερομηνία διεκπεραίωσης. Το χρονικό όριο '
                        .'ορίζεται στα «Λεπτά για διόρθωση».'],
                'allowWriterUpdateProtocolTimeInMinutes' => ['label' => 'Λεπτά για διόρθωση', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:0', 'max:525600'],
                    'help' => 'Πόσα λεπτά μετά την καταχώριση ή την τελευταία αλλαγή γίνεται διόρθωση (κενό ή 0 = χωρίς όριο).',
                    'more' => 'Ισχύει για όσους επιτρέπει η «Διόρθωση πρωτοκόλλου»· όχι για τον Διαχειριστή. Η φόρμα δείχνει πόσος χρόνος '
                        .'μένει. Κάθε αποθήκευση ξεκινά το μέτρημα από την αρχή για τα μέρη που μένουν ανοιχτά· όταν περάσει ο χρόνος και '
                        .'από την καταχώριση και από την τελευταία αλλαγή, κλειδώνει όλο. Πρωτόκολλα χωρίς θέμα μένουν ανοιχτά.'],
                'allowWriterChangeDate' => ['label' => 'Ο Συγγραφέας αλλάζει την ημερομηνία πρωτοκόλλου', 'kind' => 'bool',
                    'help' => 'Συγγραφείς και Αναθέτοντες ορίζουν την ημερομηνία πρωτοκόλλου. Αλλιώς μπαίνει η σημερινή.',
                    'more' => 'Π.χ. για έγγραφο που παραλήφθηκε χθες. Ο Διαχειριστής ορίζει πάντα την ημερομηνία όταν οι «Έλεγχοι '
                        .'καταχώρισης» είναι κλειστοί· όταν είναι ανοιχτοί, χρειάζεται κι αυτός αυτή τη ρύθμιση. Η καταχώριση από τα '
                        .'Εισερχόμενα παίρνει πάντα τη σημερινή ημερομηνία.'],
                'limitProtocolAccessList' => ['label' => 'Περιορισμός λίστας', 'kind' => 'bool',
                    'help' => 'Συγγραφείς και Αναγνώστες βλέπουν μόνο τα πρωτόκολλα που καταχώρισαν ή τους ανατέθηκαν.',
                    'more' => 'Ισχύει στη λίστα, στην αναζήτηση και στις προτάσεις των πεδίων· άλλο πρωτόκολλο δεν ανοίγει ούτε με τη '
                        .'διεύθυνσή του. Η Εκτύπωση δεν τους εμφανίζεται. Διαχειριστές και Αναθέτοντες βλέπουν όλα τα πρωτόκολλα.'],
                'allowUserChangeKeepSelect' => ['label' => 'Ελεύθερη επιλογή χρόνου διατήρησης', 'kind' => 'bool',
                    'help' => 'Η Διατήρηση των συνημμένων αλλάζει στο πρωτόκολλο. Αλλιώς είναι πάντα του φακέλου.',
                    'more' => 'Ανοιχτό: ο φάκελος προτείνει τη Διατήρηση και ο χρήστης την αλλάζει. Κλειστό: η Διατήρηση είναι του '
                        .'φακέλου, στη φόρμα πρωτοκόλλου και στα Εισερχόμενα, και το πεδίο δεν αλλάζει. Η Διατήρηση κάθε φακέλου '
                        .'ορίζεται στο «Φάκελοι και διατήρηση».'],
            ]],
            'display' => ['title' => 'Εμφάνιση και λίστες', 'icon' => 'layout-text-window', 'settings' => [
                'showRowsInPage' => ['label' => 'Γραμμές ανά σελίδα', 'kind' => 'int', 'rules' => ['required', 'integer', 'min:5', 'max:1000'],
                    'help' => 'Πόσα πρωτόκολλα δείχνει κάθε σελίδα της λίστας (5 έως 1000).',
                    'more' => 'Μόνο στη λίστα πρωτοκόλλων (Λίστα). Για την αναζήτηση: «Αποτελέσματα αναζήτησης ανά σελίδα»· για τα '
                        .'Εισερχόμενα: «E-mail ανά σελίδα».'],
                'minutesRefreshInterval' => ['label' => 'Ανανέωση της λίστας κάθε … λεπτά', 'kind' => 'int', 'rules' => ['required', 'numeric', 'min:0', 'max:1440'],
                    'help' => 'Η λίστα πρωτοκόλλων ανανεώνεται μόνη της (0 = ποτέ).',
                    'more' => 'Χρήσιμο όταν καταχωρίζουν πολλοί μαζί: τα νέα πρωτόκολλα εμφανίζονται χωρίς επαναφόρτωση. Δεκτοί και '
                        .'δεκαδικοί με τελεία (0.5 = κάθε 30 δευτερόλεπτα). Μόνο στη λίστα πρωτοκόλλων.'],
                'titleColor' => ['label' => 'Χρώμα της πάνω μπάρας', 'kind' => 'color', 'rules' => ['nullable', 'string', 'regex:/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$/'],
                    'help' => 'Κενό = το μπλε της εφαρμογής.',
                    'more' => 'Π.χ. #1f4e79 ή darkgreen. Χρήσιμο για να ξεχωρίζει μια δοκιμαστική εγκατάσταση από την κανονική.'],
                'maxRowsInFindPage' => ['label' => 'Αποτελέσματα αναζήτησης ανά σελίδα', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:100000'],
                    'help' => 'Πόσα αποτελέσματα δείχνει κάθε σελίδα της αναζήτησης (κενό = 30).',
                    'more' => 'Δεν είναι όριο: όλα τα αποτελέσματα φαίνονται, σε σελίδες. Ισχύει στη Σύνθετη αναζήτηση και στα '
                        .'αποτελέσματα του πλαισίου αναζήτησης.'],
                'maxRowsInXlsExport' => ['label' => 'Μέγιστες γραμμές εκτύπωσης και Excel', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:1000000'],
                    'help' => 'Όριο γραμμών σε μία εκτύπωση ή ένα αρχείο Excel (κενό = 5000).',
                    'more' => 'Ανάλογα με τη μνήμη του διακομιστή. Όταν ένα διάστημα έχει περισσότερα πρωτόκολλα, η εκτύπωση το '
                        .'λέει: επιλέξτε μικρότερο διάστημα ημερομηνιών.'],
            ]],
            'files' => ['title' => 'Συνημμένα', 'icon' => 'paperclip', 'settings' => [
                // R2r (owner, 2026-11-25): the e-mail's own file (registered from the inbox) first or last in the list's files
                'emailFileFirst' => ['label' => 'Το αρχείο του e-mail πρώτο στα συνημμένα της λίστας', 'kind' => 'bool',
                    'help' => 'Κλειστό (αρχικά): το e-mail (EML) μετά τα συνημμένα του, με την ημερομηνία και ώρα του αντί για το όνομα αρχείου.',
                    'more' => 'Ένα πρωτόκολλο από τα Εισερχόμενα κρατά και το ίδιο το e-mail ως αρχείο (email_ημερομηνία_ώρα.eml). Στη Λίστα '
                        .'φαίνεται ως «12/11/2026 · 10:36:26». Κλειστό: μπαίνει μετά τα πραγματικά συνημμένα (που φαίνονται πρώτα και στη '
                        .'σύντομη γραμμή), εκτός αν είναι το μόνο αρχείο. Ανοιχτό: πρώτο, όπως παλιά. Ισχύει για όλους τους χρήστες.'],
                'putStamp' => ['label' => 'Σφραγίδα στα συνημμένα (αρχική επιλογή)', 'kind' => 'bool',
                    'help' => 'Η αρχική επιλογή «Με σφραγίδα» για όσους χρήστες δεν έχουν διαλέξει: κάθε χρήστης την αλλάζει στα Συνημμένα ή στην προεπισκόπηση.',
                    'more' => 'Από την R4f τα αρχεία αποθηκεύονται όπως ήρθαν· η σφραγίδα (αριθμός, ημερομηνία, ό,τι ορίζει το σχέδιό της) '
                        .'μπαίνει σε ένα αντίγραφο όταν το αρχείο ανοίγει, κατεβαίνει ή τυπώνεται, και ξαναγίνεται μόνο του αν αλλάξουν ο αριθμός, η ημερομηνία ή η σφραγίδα. '
                        .'Κάθε χρήστης επιλέγει «Με σφραγίδα» ή το αρχείο όπως είναι, όποια κι αν είναι αυτή η ρύθμιση· η ρύθμιση ορίζει μόνο την αρχική επιλογή. Τα αρχεία που αποθηκεύτηκαν πριν έχουν τη σφραγίδα μέσα τους και δεν σφραγίζονται ξανά.'],
                'diavgeiaUrl' => ['label' => 'Διεύθυνση Διαύγειας', 'kind' => 'url', 'rules' => ['nullable', 'url:https,http', 'max:255'],
                    'help' => 'Ένα ΑΔΑ γίνεται σύνδεσμος σε αυτή τη διεύθυνση.',
                    'more' => 'Το ΑΔΑ προστίθεται στο τέλος της διεύθυνσης, γι\' αυτό αυτή τελειώνει σε /, π.χ. '
                        .'https://diavgeia.gov.gr/doc/. Κενό: το ΑΔΑ φαίνεται χωρίς σύνδεσμο.'],
                'trashRetentionDays' => ['label' => 'Κάδος: ημέρες μέχρι την οριστική διαγραφή', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:0', 'max:3650'],
                    'help' => 'Κενό = 30. 0 = μένουν μέχρι να αδειάσετε τον κάδο.',
                    'more' => 'Τα συνημμένα που διαγράφονται πάνε στον Κάδο (Διαχείριση → Κάδος) και επαναφέρονται από εκεί. Μετά από '
                        .'τόσες ημέρες διαγράφονται οριστικά· ο έλεγχος γίνεται μία φορά την ημέρα. Όσα ήταν ήδη στον κάδο πριν από '
                        .'την αναβάθμιση μετρούν από την ημέρα της αναβάθμισης.'],
            ]],
            'email' => ['title' => 'E-mail', 'icon' => 'envelope', 'settings' => [
                'defaultImapEmail' => ['label' => 'Λογαριασμός εισερχομένων', 'kind' => 'select', 'options' => self::imapAccounts(),
                    'rules' => ['nullable', 'in:'.implode(',', array_keys(self::imapAccounts()))],
                    'help' => 'Κενό: χωρίς Εισερχόμενα. Οι λογαριασμοί ορίζονται στο config/imap.php.',
                    'more' => 'Ο λογαριασμός e-mail που ανοίγουν τα Εισερχόμενα. Με κενό, τα Εισερχόμενα κρύβονται για όλους.'],
                'allowedEmailUsers' => ['label' => 'Χρήστες που καταχωρίζουν e-mail', 'kind' => 'text', 'rules' => ['nullable', 'string', 'max:2000'],
                    'help' => 'Τα username όσων βλέπουν τα Εισερχόμενα (κενό = όλοι).',
                    'more' => 'Χωρίστε τα με κενό, κόμμα ή ερωτηματικό· κεφαλαία και πεζά δεν μετρούν. Όταν η λίστα έχει ονόματα, '
                        .'χρειάζεται σε αυτήν και ο Διαχειριστής. Οι Αναγνώστες δεν βλέπουν ποτέ τα Εισερχόμενα.'],
                'daysToCheckEmailBack' => ['label' => 'E-mail των τελευταίων … ημερών', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:3650'],
                    'help' => 'Τα Εισερχόμενα δείχνουν τα e-mail αυτών των ημερών (κενό = μόνο τα σημερινά).',
                    'more' => 'Π.χ. 7: από την αρχή της ημέρας πριν από 7 ημέρες μέχρι τώρα. Όσα καταχωρίζονται φεύγουν από τα '
                        .'Εισερχόμενα ούτως ή άλλως.'],
                'emailNumFetch' => ['label' => 'E-mail ανά σελίδα', 'kind' => 'int', 'rules' => ['nullable', 'integer', 'min:1', 'max:200'],
                    'help' => 'Πόσα e-mail δείχνει κάθε σελίδα των Εισερχομένων (κενό = 1).',
                    'more' => 'Λίγα e-mail ανά σελίδα φορτώνουν γρηγορότερα· τα υπόλοιπα είναι στις επόμενες σελίδες.'],
                'emailFetchOrderDesc' => ['label' => 'Σειρά e-mail', 'kind' => 'select', 'options' => ['1' => 'Νεότερα πρώτα', '0' => 'Παλαιότερα πρώτα'], 'rules' => ['required', 'in:0,1'],
                    'help' => 'Η σειρά των e-mail στα Εισερχόμενα.',
                    'more' => 'Παλαιότερα πρώτα: καταχωρίζετε με τη σειρά που ήρθαν. Νεότερα πρώτα: βλέπετε αμέσως τα τελευταία.'],
                // R5p (owner, 2026-11-23): each e-mail in an envelope; three looks to choose from
                // R5q (owner, 2026-11-25): each user chooses in their menu; this is the choice of those who have not chosen
                'inboxEnvelope' => ['label' => 'Εμφάνιση των e-mail (αρχική επιλογή)', 'kind' => 'select',
                    'options' => ['airmail' => 'Αεροπορικός φάκελος', 'paper' => 'Χάρτινος φάκελος με γραμματόσημο', 'plain' => 'Λιτός φάκελος', 'none' => 'Καμία (μόνο μια γραμμή)'],
                    'rules' => ['nullable', 'in:airmail,paper,plain,none'],
                    'help' => 'Πώς ξεχωρίζει κάθε e-mail στα Εισερχόμενα, για όσους χρήστες δεν έχουν διαλέξει (κάθε χρήστης διαλέγει στο μενού με το όνομά του).',
                    'more' => 'Κάθε e-mail μέσα σε έναν «φάκελο», με τον αποστολέα και την ημερομηνία στη θέση της διεύθυνσης και κενό ως '
                        .'το επόμενο. Αεροπορικός: ριγέ κόκκινο-μπλε περίγραμμα. Χάρτινος: χρώμα χαρτιού, πτερύγιο και γραμματόσημο με τον '
                        .'αριθμό. Λιτός: λευκός, με ριγέ μόνο στο πάνω μέρος. Καμία: μόνο μια λεπτή γραμμή με «↓ N από M ↓» ανάμεσα στα e-mail.'],
                // R5s (owner, 2026-11-25): «Ο Φάκελος χρειάζεται για κάθε e-mail» is gone: every e-mail needs a folder
                'alwaysSendReceitForEmails' => ['label' => 'Απόδειξη σε κάθε e-mail που καταχωρίζεται', 'kind' => 'bool',
                    'help' => 'Η καταχώριση από τα Εισερχόμενα στέλνει πάντα απόδειξη στον αποστολέα.',
                    'more' => 'Το κουμπί «Καταχώριση» κρύβεται και μένει το «Καταχώριση και απόδειξη». Ένα e-mail χωρίς διεύθυνση '
                        .'απάντησης καταχωρίζεται αφού γράψετε μια διεύθυνση για την απόδειξη.'],
                'saveEmailAs' => ['label' => 'Το e-mail φυλάσσεται ως', 'kind' => 'select', 'options' => ['' => 'σελίδα (html)', 'eml' => 'αρχείο e-mail (eml)'], 'rules' => ['nullable', 'in:eml'],
                    'help' => 'Πώς φυλάσσεται το ίδιο το e-mail μαζί με το πρωτόκολλο.',
                    'more' => 'Αρχείο e-mail (eml): ακριβώς όπως ήρθε· ανοίγει με το Thunderbird ή το Outlook. Σελίδα (html): ανοίγει '
                        .'σε κάθε φυλλομετρητή, με το κείμενο του e-mail. Και στα δύο, το αντίγραφο δεν παίρνει σφραγίδα.'],
                'sendEmailOnDiekperaiosiChange' => ['label' => 'E-mail σε όποιον ανατίθεται πρωτόκολλο', 'kind' => 'bool',
                    'help' => 'Όποιος προστίθεται σε ανάθεση ενημερώνεται με e-mail.',
                    'more' => 'Για Διεκπεραίωση και για Ενημέρωση, και για τις αυτόματες αναθέσεις των φακέλων. Στέλνεται μόνο σε όσους '
                        .'προστίθενται τώρα, όχι ξανά σε όσους είχαν ήδη την ανάθεση. Το κουμπί «Ανάθεση» της φόρμας στέλνει e-mail '
                        .'ούτως ή άλλως.'],
            ]],
            'maintenance' => ['title' => 'Συντήρηση', 'icon' => 'tools', 'settings' => [
                'mysqldumpPath' => ['label' => 'Διαδρομή του mysqldump', 'kind' => 'text', 'rules' => ['nullable', 'string', 'max:255', 'regex:/(^|[\\\\\\/])(mysqldump|mariadb-dump)(\.exe)?$/i'],
                    'help' => 'Κενό: αναζητείται αυτόματα. Γίνεται δεκτό μόνο αρχείο mysqldump ή mariadb-dump.',
                    'more' => 'Το χρησιμοποιεί το «Backup» (Διαχείριση). Αν η διαδρομή δεν υπάρχει, η εφαρμογή το αναζητεί μόνη της· '
                        .'αν δεν βρει κανένα, το Backup δείχνει σφάλμα.'],
            ]],
        ];
    }

    /**
     * R6f (owner, 2026-11-25): the page as GitHub's settings: a menu of small sections under headings, one section shown at
     * a time and saved on its own. section => [heading (null: the first, untitled), title, icon, what it is for, keys].
     */
    public const SECTIONS = [
        'office' => [null, 'Υπηρεσία', 'building', 'Το όνομα της υπηρεσίας και το χρώμα της πάνω μπάρας.',
            ['ipiresiasName', 'titleColor']],
        'display' => [null, 'Λίστες και αναζήτηση', 'layout-text-window', 'Πόσες γραμμές δείχνουν η λίστα και η αναζήτηση, και κάθε πότε ανανεώνεται η λίστα.',
            ['showRowsInPage', 'minutesRefreshInterval', 'maxRowsInFindPage']],
        'numbering' => ['Πρωτόκολλο', 'Αρίθμηση', 'hash', 'Το έτος των νέων πρωτοκόλλων, από ποιον αριθμό ξεκινά και πόσο πίσω ελέγχονται οι κενοί αριθμοί.',
            ['yearInUse', 'firstProtocolNum', 'gapCheckMonths']],
        'registration' => ['Πρωτόκολλο', 'Καταχώριση', 'journal-check', 'Τι ελέγχεται και τι επιτρέπεται όταν αποθηκεύεται ένα πρωτόκολλο.',
            ['protocolValidate', 'allowEmptyProtocol', 'saveCycleSxetiko']],
        'rights' => ['Πρωτόκολλο', 'Δικαιώματα', 'shield-lock', 'Τι μπορούν να αλλάξουν οι Συγγραφείς και οι Αναθέτοντες, και ποια πρωτόκολλα βλέπουν στη λίστα.',
            ['allowWriterUpdateProtocol', 'allowWriterUpdateProtocolTimeInMinutes', 'allowWriterChangeDate', 'limitProtocolAccessList']],
        'files' => ['Αρχεία', 'Συνημμένα', 'paperclip', 'Η σφραγίδα, ο χρόνος διατήρησης, η Διαύγεια και η θέση του e-mail στη λίστα.',
            ['putStamp', 'allowUserChangeKeepSelect', 'diavgeiaUrl', 'emailFileFirst']],
        'trash' => ['Αρχεία', 'Κάδος', 'trash3', 'Πόσο μένουν στον κάδο τα διαγραμμένα συνημμένα και πρωτόκολλα.',
            ['trashRetentionDays']],
        'printing' => ['Αρχεία', 'Εκτυπώσεις και Excel', 'printer', 'Το όριο γραμμών για την εκτύπωση πρωτοκόλλου και το Excel.',
            ['maxRowsInXlsExport']],
        'mailbox' => ['E-mail', 'Λογαριασμός εισερχομένων', 'inbox', 'Από ποιον λογαριασμό έρχονται τα εισερχόμενα, ποιοι τα καταχωρίζουν και πόσο πίσω φαίνονται.',
            ['defaultImapEmail', 'allowedEmailUsers', 'daysToCheckEmailBack']],
        'inbox' => ['E-mail', 'Εισερχόμενα', 'envelope', 'Πόσα e-mail ανά σελίδα, με ποια σειρά και πώς ξεχωρίζουν.',
            ['emailNumFetch', 'emailFetchOrderDesc', 'inboxEnvelope']],
        'emailRegistration' => ['E-mail', 'Καταχώριση e-mail', 'envelope-check', 'Η απόδειξη στον αποστολέα και πώς φυλάσσεται το ίδιο το e-mail.',
            ['alwaysSendReceitForEmails', 'saveEmailAs']],
        'notifications' => ['E-mail', 'Ειδοποιήσεις', 'bell', 'Τα e-mail που στέλνει η εφαρμογή στους χρήστες.',
            ['sendEmailOnDiekperaiosiChange']],
        'backup' => ['Συντήρηση', 'Αντίγραφα ασφαλείας', 'database', 'Το πρόγραμμα που φτιάχνει τα αντίγραφα της βάσης (Διαχείριση → Backup).',
            ['mysqldumpPath']],
    ];

    /**
     * The sections in their order, each with its settings (definitions in SECTIONS' order) and the boxes they use.
     *
     * @return array<string, array{heading: ?string, title: string, icon: string, description: string, settings: array<string, array>, boxes: array<string, array{title: string, help: string}>}>
     */
    public static function groups(): array
    {
        $defs = [];
        $boxes = [];
        foreach (self::definitions() as $g) {
            $defs += $g['settings'];
            $boxes += $g['boxes'] ?? [];
        }
        $sections = [];
        foreach (self::SECTIONS as $id => [$heading, $title, $icon, $description, $keys]) {
            $settings = array_intersect_key(array_replace(array_flip($keys), $defs), array_flip($keys));
            $sections[$id] = ['heading' => $heading, 'title' => $title, 'icon' => $icon, 'description' => $description,
                'settings' => $settings, 'boxes' => array_intersect_key($boxes, array_flip(array_filter(array_column($settings, 'box'))))];
        }

        return $sections;
    }

    /** R6f: the section a setting is in (null: not a setting of the page) */
    public static function sectionOf(string $key): ?string
    {
        foreach (self::SECTIONS as $id => $section) {
            if (in_array($key, $section[4], true)) {
                return $id;
            }
        }

        return null;
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

    /** @return array<string, string> '' => none, then the accounts of config/imap.php (their 'label', else the name) */
    public static function imapAccounts(): array
    {
        $accounts = ['' => '—'];
        foreach ((array) config('imap.accounts', []) as $name => $account) {
            $accounts[(string) $name] = (string) ($account['label'] ?? $name);
        }

        return $accounts;
    }
}
