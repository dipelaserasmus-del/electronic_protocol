<?php

namespace App\Services\Protocols;

/**
 * R5c: an all-or-nothing save (e-mail registration) could not store one of its files; nothing was kept, so the user
 * can simply try again (the e-mail stays in the inbox).
 */
final class AttachmentsNotSaved extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Δεν ήταν δυνατή η αποθήκευση των συνημμένων αρχείων.', 0, $previous);
    }
}
