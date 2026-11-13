<?php

namespace App\Support\Imap;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;

/**
 * B07: move an e-mail between IMAP folders reliably.
 *
 * webklex/php-imap 6.2 `Message::move()` first EXAMINEs the target folder — which, per RFC 3501, makes the
 * target the selected (read-only) mailbox — but its folder cache still believes the source is open, so it
 * does not re-select it. The MOVE then runs against the wrong, read-only mailbox and the final EXPUNGE is
 * rejected ("NO EXPUNGE failed. Command not valid in this state"). Users saw an error after the protocol was
 * already stored, while the e-mail stayed in the inbox.
 *
 * Here the source folder is explicitly (re)selected and the message is moved by UID; the protocol layer uses
 * IMAP MOVE when available and falls back to COPY + \Deleted + EXPUNGE on the correctly selected mailbox.
 */
class MessageMover
{
    /**
     * Find a folder by its full path, then by its name. (Client::getFolder() decides by looking for the configured
     * delimiter, '/' by default, so a path like "INBOX.inProtocol" on a '.'-delimited server was never found and
     * the app tried to create it again → "already exists" error.)
     */
    public static function folder(Client $client, string $folder): ?Folder
    {
        return $client->getFolderByPath($folder, false, true) ?? $client->getFolderByName($folder, true);
    }

    /** The folder, created if it does not exist yet. */
    public static function ensureFolder(Client $client, string $folder): Folder
    {
        // expunge=false: createFolder() would otherwise EXPUNGE with no mailbox selected → "Command not valid in this state"
        return self::folder($client, $folder) ?? $client->createFolder($folder, false);
    }

    public static function move(Client $client, string $sourceFolder, int|string $uid, string $targetFolder): void
    {
        $target = self::ensureFolder($client, $targetFolder)->path;
        $source = self::ensureFolder($client, $sourceFolder)->path;

        $client->openFolder($source, true);   // force a real SELECT (read-write) of the source
        $client->getConnection()->moveMessage($target, $uid, null, IMAP::ST_UID)->validatedData();
    }
}
