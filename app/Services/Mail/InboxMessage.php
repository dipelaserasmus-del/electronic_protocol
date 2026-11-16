<?php

namespace App\Services\Mail;

use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Message;

/** A message on the inbox page: its UID and the parsed e-mail. */
final class InboxMessage
{
    public function __construct(public readonly int $uid, public readonly Message $mail) {}

    /** Seconds since 1970 from the Date header (0 when missing or unreadable). */
    public function timestamp(): int
    {
        try {
            return $this->mail->getHeader(HeaderConsts::DATE)?->getDateTime()?->getTimestamp() ?? 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function hasHtml(): bool
    {
        return strlen((string) $this->mail->getHtmlContent()) > 0;
    }
}
