<?php

namespace Tests\Support;

use App\Services\Mail\Mailbox;
use App\Services\Mail\MailboxConnector;

/**
 * In-memory mailbox for tests: messages by UID, a UIDVALIDITY, and a record of every download.
 * Bind it with FakeMailbox::install($this->app) and the inbox page reads from it instead of an IMAP server.
 */
final class FakeMailbox implements Mailbox
{
    /** @var array<int, string> */
    public array $messages = [];

    public string $validity = '1001';

    /** @var list<list<int>> UIDs asked for by each fetchRaw() call */
    public array $fetches = [];

    public bool $unreachable = false;

    /** @var list<array{int, string}> [uid, folder] of every move */
    public array $moves = [];

    /** R5c: the next move fails (server refuses) */
    public bool $refuseMoves = false;

    /** R5m: opening fails with this (a timeout, a refused login) instead of "connection refused" */
    public ?\Throwable $failWith = null;

    public static function install(\Illuminate\Foundation\Application $app): self
    {
        $mailbox = new self;
        $app->instance(MailboxConnector::class, new class($mailbox) extends MailboxConnector {
            public function __construct(private FakeMailbox $mailbox) {}

            public function open(string $account, string $folder): Mailbox
            {
                if ($this->mailbox->failWith !== null) {
                    throw $this->mailbox->failWith;
                }
                if ($this->mailbox->unreachable) {
                    throw new \RuntimeException('connection refused');
                }

                return $this->mailbox;
            }
        });

        return $mailbox;
    }

    public function id(): string
    {
        return 'test/INBOX';
    }

    public function uidValidity(): string
    {
        return $this->validity;
    }

    /** how many times the folder was searched (R5f review: the badge's count) */
    public int $searches = 0;

    public function uidsSince(\DateTimeInterface $since): array
    {
        $this->searches++;
        $uids = array_keys($this->messages);
        sort($uids);

        return $uids;
    }

    public function fetchRaw(array $uids): array
    {
        $this->fetches[] = $uids;

        return array_intersect_key($this->messages, array_flip($uids));
    }

    public function move(int $uid, string $folder): void
    {
        if ($this->refuseMoves) {
            throw new \RuntimeException('NO MOVE failed');
        }
        $this->moves[] = [$uid, $folder];
        unset($this->messages[$uid]);
    }

    /** UIDs downloaded so far, in order. @return list<int> */
    public function downloaded(): array
    {
        return array_merge([], ...$this->fetches);
    }
}
