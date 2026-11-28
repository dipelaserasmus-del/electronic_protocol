<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Services\Search\SearchCriteria;
use App\Support\SettingsCatalog;

/**
 * X02: an audit log entry as people read it, on the page and in the CSV: the action and what it is about in Greek,
 * each change as «field: old → new» with the field's label (the protocol form's, the settings page's).
 */
final class AuditPresenter
{
    public const ACTIONS = [
        'created' => 'Δημιουργία',
        'updated' => 'Αλλαγή',
        'trashed' => 'Στον κάδο',
        'restored' => 'Επαναφορά',
        'purged' => 'Οριστική διαγραφή',
        'deleted' => 'Διαγραφή',
        'login' => 'Σύνδεση',
        'logout' => 'Αποσύνδεση',
        'login_failed' => 'Αποτυχημένη σύνδεση',
        'downloaded' => 'Λήψη αρχείου',
        'email_registered' => 'Καταχώριση e-mail',
        'folders_merged' => 'Συγχώνευση φακέλων',
    ];

    /** Bootstrap colour of each action's badge */
    public const COLOURS = [
        'created' => 'success', 'updated' => 'primary', 'trashed' => 'warning', 'restored' => 'info', 'purged' => 'danger',
        'deleted' => 'danger', 'login' => 'secondary', 'logout' => 'secondary', 'login_failed' => 'danger',
        'downloaded' => 'light', 'email_registered' => 'success', 'folders_merged' => 'primary',
    ];

    public const TYPES = [
        'protocol' => 'Πρωτόκολλο',
        'attachment' => 'Συνημμένο',
        'assignment' => 'Ανάθεση',
        'folder' => 'Φάκελος',
        'folder_assignment' => 'Αυτόματη ανάθεση φακέλου',
        'user' => 'Χρήστης',
        'setting' => 'Ρύθμιση',
    ];

    /** Fields without a label elsewhere */
    private const FIELDS = [
        'protocolnum' => 'Αριθμός', 'etos' => 'Έτος', 'fakelos' => 'Φάκελος', 'user_id' => 'Χρήστης', 'protocol_id' => 'Πρωτόκολλο',
        'diekperaiosi' => 'Ανάθεση', 'diekp_eos' => 'Διεκπεραίωση έως', 'reply_to_email' => 'Απόδειξη σε', 'email_date' => 'Ημ/νία e-mail',
        'flags' => 'Σημάνσεις', 'type' => 'Είδος', 'savedPath' => 'Αρχείο στον δίσκο', 'mimeType' => 'Τύπος αρχείου', 'size' => 'Μέγεθος',
        'sha256' => 'Αποτύπωμα', 'keep' => 'Διατήρηση', 'expires' => 'Λήξη', 'stamp_place' => 'Θέση σφραγίδας',
        'stamp_opaque' => 'Αδιαφανής σφραγίδα', 'stamp_in_file' => 'Σφραγίδα στο αρχείο', 'deleted_at' => 'Στον κάδο από',
        'deleted_by' => 'Στον κάδο από τον χρήστη', 'trashed_from' => 'Αρχική θέση', 'protocol_label' => 'Πρωτόκολλο',
        'name' => 'Όνομα', 'username' => 'Όνομα χρήστη', 'email' => 'E-mail', 'role_id' => 'Ρόλος', 'password' => 'Κωδικός',
        'active' => 'Ενεργός', 'describe' => 'Περιγραφή', 'remarks' => 'Παρατηρήσεις', 'keep_alt' => 'Διατήρηση (εναλλακτική)',
        'keepvalue_id' => 'Φάκελος', 'value' => 'Τιμή',
    ];

    /** Labels that depend on what the entry is about (a user's «name» is not an attachment's) */
    private const TYPE_FIELDS = [
        'user' => ['name' => 'Ονοματεπώνυμο', 'email' => 'E-mail'],
        'attachment' => ['name' => 'Όνομα αρχείου'],
        'folder' => ['fakelos' => 'Κωδικός φακέλου', 'keep' => 'Διατήρηση (έτη)'],
        'folder_assignment' => ['type' => 'Είδος (d: διεκπεραίωση, e: ενημέρωση)'],
        'assignment' => ['type' => 'Είδος (d: διεκπεραίωση, e: ενημέρωση)'],
    ];

    /** Longest value shown on the page (the CSV has it whole) */
    public const SHORT = 120;

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function type(?string $type): string
    {
        return $type === null ? '' : (self::TYPES[$type] ?? $type);
    }

    /** What the entry is about, in words: «Πρωτόκολλο 8/2026», «Συνημμένο απόφαση.pdf», «Ρύθμιση Ενεργό έτος …» */
    public static function subject(AuditEntry $e): string
    {
        $c = $e->context ?? [];
        $what = match ($e->entity_type) {
            'protocol' => $c['ref'] ?? ('#'.$e->entity_id),
            'attachment' => $c['name'] ?? $c['ada'] ?? ('#'.$e->entity_id),
            'setting' => self::settingLabel($c['key'] ?? ''),
            'folder', 'folder_assignment' => $c['folder'] ?? $c['into'] ?? ('#'.$e->entity_id),
            'user' => $c['username'] ?? self::userName($e->entity_id) ?? $c['typed'] ?? '',
            default => $e->entity_id ? '#'.$e->entity_id : '',
        };
        if ($e->action === 'folders_merged') {
            $what = ($c['from'] ?? '?').' → '.($c['into'] ?? '?');
        }
        if ($e->action === 'login_failed' && $e->entity_id === null) {
            $what = $c['typed'] ?? '';
        }

        return trim(self::type($e->entity_type).' '.$what);
    }

    /** The field's label: for what the entry is about, the protocol form's, or the column's name */
    public static function field(string $field, ?string $type = null): string
    {
        return self::TYPE_FIELDS[$type][$field] ?? SearchCriteria::TEXT_FIELDS[$field] ?? SearchCriteria::DATE_FIELDS[$field]
            ?? self::FIELDS[$field] ?? $field;
    }

    /**
     * Each change as [label, old, new] (values as text; $short: cut at SHORT characters for the page)
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function changes(AuditEntry $e, bool $short = true): array
    {
        $out = [];
        foreach ($e->changes ?? [] as $field => [$old, $new]) {
            $out[] = [self::field((string) $field, $e->entity_type), self::value(self::named((string) $field, $old), $short), self::value(self::named((string) $field, $new), $short)];
        }

        return $out;
    }

    /** What else the entry says (a download with the stamp, how many files an e-mail brought, …) */
    public static function notes(AuditEntry $e): string
    {
        $c = $e->context ?? [];
        $notes = array_filter([
            ! empty($c['automatic']) ? 'αυτόματα' : null,
            ! empty($c['console']) ? 'από τη γραμμή εντολών' : null,
            ! empty($c['stamped']) ? 'με σφραγίδα' : null,
            isset($c['attachments']) && $e->action !== 'trashed' ? 'συνημμένα: '.$c['attachments'] : null,
            isset($c['attachments']) && $e->action === 'trashed' ? 'με '.$c['attachments'].' συνημμένα' : null,
            isset($c['was']) ? 'αρχικός αριθμός '.$c['was'] : null,
            ! empty($c['files_not_back']) ? 'αρχεία που δεν επανήλθαν: '.$c['files_not_back'] : null,
            $e->action === 'folders_merged' ? 'πρωτόκολλα: '.($c['protocols'] ?? 0).', αρχεία: '.(($c['moved'] ?? 0) + ($c['renamed'] ?? 0)) : null,
            ! empty($c['remember']) ? 'να με θυμάται' : null,
        ]);

        return implode(' · ', $notes);
    }

    /** A user's or role's id as its name (as it is now; «#id» when gone) */
    private static function named(string $field, mixed $v): mixed
    {
        if ($v === null || $v === '') {
            return $v;
        }

        return match ($field) {
            'user_id', 'deleted_by' => self::userName((int) $v) ?? '#'.$v,
            'active', 'stamp_opaque', 'stamp_in_file' => (bool) $v,   // stored as 1 / 0: ναι / όχι
            'role_id' => self::lookup('roles', fn () => \App\Models\Role::pluck('role', 'id')->all())[(int) $v] ?? '#'.$v,
            default => $v,
        };
    }

    private static function userName(?int $id): ?string
    {
        return $id === null ? null : (self::lookup('users', fn () => \App\Models\User::pluck('username', 'id')->all())[$id] ?? null);
    }

    /** @var array<string, array> users' and roles' names, read once per page or export (fresh()) */
    private static array $loaded = [];

    /** Forget the names read before (the page calls it each time it is drawn: users change) */
    public static function fresh(): void
    {
        self::$loaded = [];
    }

    private static function lookup(string $name, callable $load): array
    {
        return self::$loaded[$name] ??= $load();
    }

    private static function value(mixed $v, bool $short): string
    {
        $text = match (true) {
            $v === null || $v === '' => '—',
            is_bool($v) => $v ? 'ναι' : 'όχι',
            is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            // a DATE / DATETIME column as people write it: 28/11/2026 (12:15)
            is_string($v) && preg_match('~^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2})(?::\d{2})?)?$~', $v, $m) === 1
                => "{$m[3]}/{$m[2]}/{$m[1]}".(isset($m[4]) ? " {$m[4]}" : ''),
            default => (string) $v,
        };

        return $short && mb_strlen($text) > self::SHORT ? mb_substr($text, 0, self::SHORT - 1).'…' : $text;
    }

    private static function settingLabel(string $key): string
    {
        static $labels = null;
        $labels ??= array_map(fn ($s) => $s['label'], array_merge(...array_values(array_column(SettingsCatalog::groups(), 'settings'))));

        return $labels[$key] ?? $key;
    }
}
