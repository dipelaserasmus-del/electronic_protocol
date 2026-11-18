<?php

namespace App\Services\Mail;

use App\Support\Imap\MessageMover;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;

/** A folder on the IMAP server (webklex/php-imap): one search for the list, one fetch for all missing messages. */
final class ImapMailbox implements Mailbox
{
    private Folder $folder;

    private ?string $uidValidity = null;

    public function __construct(private Client $client, private string $account, string $folder)
    {
        $this->folder = MessageMover::ensureFolder($client, $folder);
    }

    public function id(): string
    {
        return $this->account.'/'.$this->folder->path;
    }

    public function uidValidity(): string
    {
        return $this->uidValidity ??= (string) ($this->folder->examine()['uidvalidity'] ?? '0');
    }

    public function uidsSince(\DateTimeInterface $since): array
    {
        $uids = $this->folder->query()->setSequence(IMAP::ST_UID)->since($since->format('d-m-Y'))->search()
            ->map(fn ($uid) => (int) $uid)->all();
        sort($uids);

        return array_values($uids);
    }

    public function fetchRaw(array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $this->client->openFolder($this->folder->path);
        $connection = $this->client->getConnection();
        // the same two parts the inbox always fetched (header + text); the text fetch marks the message as seen
        $headers = $connection->headers($uids, 'RFC822', IMAP::ST_UID)->validatedData();
        $bodies = $connection->content($uids, 'RFC822', IMAP::ST_UID)->validatedData();

        $raw = [];
        foreach ($uids as $uid) {
            if (isset($headers[$uid])) {
                $raw[$uid] = self::join($headers[$uid], $bodies[$uid] ?? '');
            }
        }

        return $raw;
    }

    public function move(int $uid, string $folder): void
    {
        MessageMover::move($this->client, $this->folder->path, $uid, $folder);
    }

    /**
     * Header + body as one e-mail. The header comes back without the empty line that separates it from the body;
     * without that line the body would be read as more headers.
     */
    public static function join(string $header, string $body): string
    {
        return rtrim($header, "\r\n")."\r\n\r\n".$body;
    }
}
