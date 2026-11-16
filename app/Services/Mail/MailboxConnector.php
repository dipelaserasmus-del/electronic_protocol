<?php

namespace App\Services\Mail;

use Webklex\IMAP\Facades\Client;

/** Opens a folder of a configured IMAP account. Bound in the container so tests can use an in-memory mailbox. */
class MailboxConnector
{
    /** @throws \Throwable when the server can't be reached or refuses the login */
    public function open(string $account, string $folder): Mailbox
    {
        $client = Client::account($account);
        $client->connect();

        return new ImapMailbox($client, $account, $folder);
    }
}
