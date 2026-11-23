<?php

namespace App\Support\Mail;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * L03: while the local real-data copy sends through the test mailbox's SMTP (TEST_SMTP_HOST, see config/mail.php),
 * every e-mail reaches only the addresses of TEST_MAIL_ONLY_TO (default: the test mailbox itself). The copy holds real
 * staff and sender addresses: those are taken off, and the message goes to the first allowed address instead, its
 * subject naming whom it was for. With no allowed address nothing is sent. Listens to MessageSending, so it covers
 * every mail the app sends (receipts, assignment notices, password resets).
 */
final class TestMailRedirect
{
    public const SUBJECT_PREFIX = '[ΔΟΚΙΜΗ]';

    /** @return false|null false = do not send; null = send (possibly with fewer recipients) */
    public function handle(MessageSending $event): ?bool
    {
        if (! config('mail.test.enabled')) {
            return null;
        }
        $allowed = array_values(array_unique(array_map(
            fn ($a) => mb_strtolower(trim((string) $a)),
            array_filter((array) config('mail.test.only_to', []), fn ($a) => trim((string) $a) !== '')
        )));
        if ($allowed === []) {
            Log::warning('Test SMTP: e-mail not sent, TEST_MAIL_ONLY_TO is empty');

            return false;
        }
        $message = $event->message;
        if (! $message instanceof Email) {
            return false;   // unknown message type: never risk a real recipient
        }

        $removed = [];
        $kept = [];
        foreach (['to' => $message->getTo(), 'cc' => $message->getCc(), 'bcc' => $message->getBcc()] as $kind => $addresses) {
            $kept[$kind] = array_values(array_filter($addresses, function (Address $a) use ($allowed, &$removed) {
                if (in_array(mb_strtolower($a->getAddress()), $allowed, true)) {
                    return true;
                }
                $removed[] = $a->getAddress();

                return false;
            }));
        }
        if ($removed === []) {
            return null;
        }

        if ($kept['to'] === [] && $kept['cc'] === [] && $kept['bcc'] === []) {
            $kept['to'] = [new Address($allowed[0])];
        }
        $headers = $message->getHeaders();
        foreach (['To', 'Cc', 'Bcc'] as $name) {
            $headers->remove($name);
        }
        if ($kept['to'] !== []) {
            $message->to(...$kept['to']);
        }
        if ($kept['cc'] !== []) {
            $message->cc(...$kept['cc']);
        }
        if ($kept['bcc'] !== []) {
            $message->bcc(...$kept['bcc']);
        }
        $message->subject(self::SUBJECT_PREFIX.' προς: '.implode(', ', array_unique($removed)).' · '.$message->getSubject());
        $headers->addTextHeader('X-Test-Original-Recipients', implode(', ', array_unique($removed)));

        return null;
    }
}
