<?php

namespace App\Services\Protocols;

use App\Models\Protocol;

/** R1a: what a save did, for the message shown to the user (plain text lines; the screen escapes them). */
final class SaveResult
{
    /** @var list<string> done as asked (e-mails sent, placeholder replaced) */
    public array $messages = [];

    /** @var list<string> saved, but something needs the user's attention (a file not stored, an e-mail not sent) */
    public array $warnings = [];

    public int $attachmentsAdded = 0;

    public function __construct(public Protocol $protocol, public bool $replacedPlaceholder = false) {}
}
