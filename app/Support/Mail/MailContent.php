<?php

namespace App\Support\Mail;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * R5c: what the inbox shows and registers of an e-mail, read once from the parsed message: senders and recipients,
 * date, subject, text, whether there is an HTML part, and the attachments with their names, types and sizes (from the
 * MIME structure, so nothing is downloaded or saved to find them).
 *
 * summary() is a plain array, so it can be cached: the inbox page re-renders on every click (Livewire) and parsing a
 * 20 MB e-mail each time would be slow. Attachments are addressed by a key: the part's position among the
 * attachments ("0", "1", …), the same key the old inbox used.
 *
 * R5b: a winmail.dat (TNEF, Outlook) is opened: its files are shown and stored instead of it (keys "<part>-<n>", e.g.
 * "0-1"), and its text, HTML and subject are used when the e-mail itself has none (a read receipt: what it reports).
 * A winmail.dat that can't be read stays a plain attachment, as before.
 */
final class MailContent
{
    /** Longest text kept for the page (the full e-mail is always stored with the protocol). */
    public const TEXT_MAX = 100_000;

    /**
     * @return array{from_name: ?string, from_email: ?string, to: list<string>, cc: list<string>, reply_to: ?string,
     *     date: ?string, subject: string, text: string, has_html: bool,
     *     attachments: list<array{key: string, name: string, mime: string, size: int}>, winmail: ?array{class: string, report: bool}}
     */
    public static function summary(Message $mail): array
    {
        $from = self::addresses($mail, HeaderConsts::FROM)[0] ?? null;
        $replyTo = self::addresses($mail, HeaderConsts::REPLY_TO)[0] ?? null;
        ['files' => $files, 'tnef' => $tnef] = self::contents($mail);
        $attachments = [];
        foreach ($files as $key => $file) {
            $attachments[] = ['key' => (string) $key, 'name' => $file['shown'], 'mime' => $file['mime'], 'size' => $file['size']()];
        }
        $text = (string) $mail->getTextContent();
        $html = strlen((string) $mail->getHtmlContent()) > 0;
        $subject = self::clean((string) $mail->getHeaderValue(HeaderConsts::SUBJECT));

        return [
            'from_name' => $from ? (self::clean($from->getName()) ?: null) : null,
            'from_email' => $from ? (self::clean($from->getEmail()) ?: null) : null,
            'to' => array_map(self::label(...), self::addresses($mail, HeaderConsts::TO)),
            'cc' => array_map(self::label(...), self::addresses($mail, HeaderConsts::CC)),
            'reply_to' => $replyTo ? (self::clean($replyTo->getEmail()) ?: null) : null,
            'date' => self::date($mail)?->format(DATE_ATOM),
            'subject' => $subject !== '' ? $subject : self::clean($tnef['subject'] ?? ''),
            'text' => mb_substr(trim($text) !== '' ? $text : (string) ($tnef['text'] ?? ''), 0, self::TEXT_MAX),
            'has_html' => $html || ($tnef['html'] ?? null) !== null,
            'attachments' => $attachments,
            'winmail' => $tnef === null ? null : ['class' => $tnef['class'], 'report' => $tnef['report']],
        ];
    }

    /** "Name <email>", or the address alone (Αρχή έκδοσης, as the old inbox filled it in). */
    public static function sender(array $summary): string
    {
        $email = $summary['from_email'] ?? '';
        $name = $summary['from_name'] ?? '';

        return $name !== '' ? trim($name.($email !== '' ? " <$email>" : '')) : $email;
    }

    /**
     * One attachment's name, type and content, by its key (null when there is none).
     *
     * @return array{name: string, mime: string, content: string}|null
     */
    public static function attachment(Message $mail, string $key): ?array
    {
        $file = self::contents($mail)['files'][$key] ?? null;

        // the name as sent: ProtocolWriter cleans it for storing (FileNames, F08)
        return $file === null ? null : ['name' => $file['name'], 'mime' => $file['mime'], 'content' => $file['content']()];
    }

    /** The HTML part, as UTF-8; R5b: or the HTML inside a winmail.dat (null when there is none). */
    public static function html(Message $mail): ?string
    {
        $html = (string) $mail->getHtmlContent();
        if ($html !== '') {
            return $html;
        }
        return self::contents($mail)['tnef']['html'] ?? null;
    }

    /**
     * The files of an e-mail by key: each attachment part, or the files inside a readable winmail.dat (R5b), and the
     * message found in the (first) winmail.dat. Contents and sizes are read only when asked.
     *
     * @return array{files: array<string, array{name: string, shown: string, mime: string, content: \Closure, size: \Closure}>, tnef: ?array}
     */
    private static function contents(Message $mail): array
    {
        $files = [];
        $found = null;
        foreach (self::parts($mail) as $i => $part) {
            $tnef = self::isTnef($part) ? Tnef::decode((string) $part->getContent(), self::zone()) : null;
            if ($tnef === null) {
                $files[(string) $i] = ['name' => (string) $part->getFilename(), 'shown' => self::name($part),
                    'mime' => strtolower((string) $part->getContentType()), 'content' => fn () => (string) $part->getContent(),
                    'size' => fn () => self::size($part)];
                continue;
            }
            $found ??= $tnef;
            foreach ($tnef['attachments'] as $j => $inner) {
                $shown = self::clean($inner['name']);
                $files["$i-$j"] = ['name' => $inner['name'], 'shown' => $shown !== '' ? $shown : \App\Support\FileNames::clean(null, $inner['mime']),
                    'mime' => $inner['mime'], 'content' => fn () => $inner['content'], 'size' => fn () => strlen($inner['content'])];
            }
        }

        return ['files' => $files, 'tnef' => $found];
    }

    /** application/ms-tnef, or a part named winmail.dat (some servers send it as application/octet-stream) */
    private static function isTnef(IMessagePart $part): bool
    {
        $type = strtolower((string) $part->getContentType());

        return in_array($type, ['application/ms-tnef', 'application/vnd.ms-tnef'], true)
            || strtolower(trim((string) $part->getFilename())) === 'winmail.dat';
    }

    private static function zone(): \DateTimeZone
    {
        return \App\Services\Mail\EmailRegistration::zone();
    }

    /** The Date header (null when missing or unreadable). */
    public static function date(Message $mail): ?\DateTimeImmutable
    {
        try {
            $date = $mail->getHeader(HeaderConsts::DATE)?->getDateTime();
        } catch (\Throwable) {
            return null;
        }

        return $date ? \DateTimeImmutable::createFromInterface($date) : null;
    }

    /** @return array<int, IMessagePart> */
    private static function parts(Message $mail): array
    {
        return array_values($mail->getAllAttachmentParts());
    }

    /** The name shown; a part without a usable name gets the name it will be stored with ("συνημμένο.pdf", F08). */
    private static function name(IMessagePart $part): string
    {
        $name = self::clean((string) $part->getFilename());

        return $name !== '' ? $name : \App\Support\FileNames::clean(null, $part->getContentType());
    }

    /** Size of the decoded content in bytes (what will be stored), without keeping it in memory. */
    private static function size(IMessagePart $part): int
    {
        $stream = $part->getBinaryContentStream();
        if ($stream === null) {
            return 0;
        }
        $size = 0;
        while (! $stream->eof()) {
            $size += strlen($stream->read(1 << 16));
        }
        $stream->rewind();

        return $size;
    }

    /** @return list<\ZBateson\MailMimeParser\Header\Part\AddressPart> */
    private static function addresses(Message $mail, string $header): array
    {
        $h = $mail->getHeader($header);

        return $h instanceof AddressHeader && $h->getRawValue() ? array_values($h->getAddresses()) : [];
    }

    private static function label(\ZBateson\MailMimeParser\Header\Part\AddressPart $a): string
    {
        $name = self::clean($a->getName());
        $email = self::clean($a->getEmail());

        return $name !== '' ? trim("$name <$email>") : $email;
    }

    /** One line, valid UTF-8, without control characters. */
    private static function clean(?string $text): string
    {
        $text = mb_scrub((string) $text, 'UTF-8');

        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '');
    }
}
