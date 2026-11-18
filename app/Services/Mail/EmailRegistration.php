<?php

namespace App\Services\Mail;

use App\Models\Config;
use App\Models\User;
use App\Services\Protocols\ProtocolWriter;
use App\Services\Protocols\SaveResult;
use App\Support\Mail\MailContent;
use App\Support\Mail\MailParser;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * R5c: registering an inbox e-mail as an incoming protocol (was ProtocolController::storeFromEmail).
 *
 * Saving goes through ProtocolWriter like every other protocol (cleaning, who may change what, validation, numbering,
 * assignments, stamp, receipt). What is particular to e-mails:
 *  - the e-mail itself is stored with the protocol, as received (.eml) or as a page (.html), setting saveEmailAs;
 *  - the attachments the user kept ticked are stored too, under the chosen Φάκελος, which is then required;
 *  - all or nothing (R5a): if one file can't be stored, nothing is kept and the e-mail stays in the inbox;
 *  - once registered, the e-mail moves to the "registered" folder of the mailbox (inProtocol). If that move fails the
 *    protocol stays (it is registered) and the user is told not to register the e-mail again.
 */
final class EmailRegistration
{
    public function __construct(private ProtocolWriter $writer) {}

    /**
     * @param array<string, mixed> $input the form's fields (ProtocolWriter names; assignments as ["d14", "e10"])
     * @param list<string> $keys attachment keys to store (MailContent::summary)
     * @param string|null $receiptTo send the receipt ("απόδειξη παραλαβής") to this address
     *
     * @throws ValidationException fields the user can fix (keys as in the form)
     * @throws \App\Services\Protocols\AttachmentsNotSaved nothing was registered
     * @throws \Illuminate\Auth\Access\AuthorizationException the user may not register protocols
     */
    public function register(User $user, string $raw, array $input, array $keys, ?string $receiptTo, ?Mailbox $mailbox = null, ?int $uid = null): SaveResult
    {
        $mail = MailParser::parse($raw);
        $date = MailContent::date($mail);
        self::checkFolder($input, $keys);

        $files = [$this->copyOfEmail($raw, $mail, $date)];
        $missing = 0;
        foreach (array_values(array_unique($keys)) as $key) {
            $part = MailContent::attachment($mail, (string) $key);
            if ($part === null) {
                $missing++;
                continue;
            }
            // the key in the stored name keeps two attachments with the same name apart (as before)
            $files[] = ['content' => $part['content'], 'name' => $part['name'], 'mime' => $part['mime'], 'prefix' => $key.'_'];
        }

        // review of R5c: the number is given on save, dated today, in the year in use, whoever registers (also users who
        // may choose the number or the date in the protocol form: the inbox has neither field)
        $input['etos'] = (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
        $input['protocoldate'] = Carbon::today()->format('d/m/Y');
        $input['protocolnum'] = '';
        $input['paratiriseis'] = trim((string) ($input['paratiriseis'] ?? '')) !== '' ? $input['paratiriseis'] : 'παρελήφθη με email';
        $input['reply_to_email'] = $receiptTo;
        $input['email_date'] = ($date ?? new \DateTimeImmutable())->setTimezone(self::zone())->format('d/m/Y H:i:s');

        $result = $this->writer->create($user, $input, $files, allOrNothing: true);

        if ($missing) {
            $result->warnings[] = "Δεν βρέθηκαν $missing από τα συνημμένα του email: επισυνάψτε τα στο πρωτόκολλο από τον δίσκο σας.";
        }
        if ($mailbox !== null && $uid !== null) {
            try {
                $mailbox->move($uid, (string) config('imapFolders.inProtocol'));
            } catch (\Throwable $e) {
                report($e);
                $result->warnings[] = 'Το email δεν μεταφέρθηκε στον φάκελο των πρωτοκολλημένων και παραμένει στα εισερχόμενα: '
                    .'το πρωτόκολλο έχει ήδη καταχωριστεί, μην το καταχωρίσετε ξανά.';
            }
        }

        return $result;
    }

    /**
     * The folder: needed for the attachments' place in the archive (old inbox: a message in the browser only), or always
     * with the setting alwaysShowFakelosInViewEmails. Public so the page can say it before anything else (R5d).
     *
     * @throws ValidationException
     */
    public static function checkFolder(array $input, array $keys): void
    {
        if (trim((string) ($input['fakelos'] ?? '')) !== '') {
            return;
        }
        if ($keys !== []) {
            throw ValidationException::withMessages(['fakelos' => 'Για να καταχωρίσετε email με συνημμένα αρχεία επιλέξτε Φάκελο (ή αφαιρέστε το ✓ από τα συνημμένα).']);
        }
        if (Config::getConfigValueOf('alwaysShowFakelosInViewEmails')) {
            throw ValidationException::withMessages(['fakelos' => 'Για να καταχωρίσετε ένα email επιλέξτε Φάκελο.']);
        }
    }

    /** The e-mail itself, stored with the protocol: as received (.eml), or as a page (.html) when saveEmailAs is empty. */
    private function copyOfEmail(string $raw, \ZBateson\MailMimeParser\Message $mail, ?\DateTimeImmutable $date): array
    {
        $stamp = ($date ?? new \DateTimeImmutable())->format('Ymd_His');
        $as = (string) Config::getConfigValueOf('saveEmailAs');
        $row = $as !== ''
            ? ['content' => $raw, 'name' => "email_$stamp.$as", 'mime' => 'message/rfc822']
            // R5b review: with the text the inbox shows (a winmail.dat's text when the e-mail itself has none)
            : ['content' => view('viewEmail', ['mailMessage' => $mail, 'bodyText' => MailContent::summary($mail)['text']])->render(),
                'name' => "email_$stamp.html", 'mime' => 'text/html'];

        // never stamped; without a folder it goes to arxeio/emails (as before)
        return $row + ['stamp' => false, 'dir' => 'arxeio/emails'];
    }

    /** The time zone of the settings (timeZone), for the dates shown and filled in. */
    public static function zone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone((string) (Config::getConfigValueOf('timeZone') ?: config('app.timezone')));
        } catch (\Throwable) {
            return new \DateTimeZone((string) config('app.timezone'));
        }
    }

    /**
     * The form of a message as the inbox fills it in (the old inbox's defaults): subject, the time and date it was sent
     * as Αριθ./Ημνία εισερχομένου, the sender as Αρχή έκδοσης, the user as Παραλήπτης, the start of the text as Περίληψη.
     *
     * @return array<string, mixed>
     */
    public static function defaults(array $summary, User $user, ?string $keep = null): array
    {
        $date = $summary['date'] ? (new \DateTimeImmutable($summary['date']))->setTimezone(self::zone()) : null;

        return [
            'fakelos' => '',
            'thema' => $summary['subject'],
            'in_num' => $date?->format('H:i:s') ?? '',
            'in_date' => $date?->format('d/m/Y') ?? Carbon::today()->format('d/m/Y'),
            'in_topos_ekdosis' => '',
            'in_arxi_ekdosis' => MailContent::sender($summary),
            'in_paraliptis' => (string) $user->name,
            'in_perilipsi' => mb_substr(trim((string) preg_replace('~^\s+|\s+$~u', '', $summary['text'])), 0, 250),
            'keywords' => '',
            'keep' => (string) $keep,
            'diekp_eos' => '',
            'reply_to_email' => $summary['reply_to'] ?: ($summary['from_email'] ?? ''),
            'files' => array_fill_keys(array_column($summary['attachments'], 'key'), true),
            'handle' => [],
            'inform' => [],
        ];
    }
}
