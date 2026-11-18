<?php

namespace App\Services\Mail;

/**
 * One mailbox folder on the mail server, as far as the inbox page needs it. ImapMailbox talks to the real server;
 * tests use an in-memory one.
 */
interface Mailbox
{
    /** Name of the account + folder, for cache keys. */
    public function id(): string;

    /**
     * IMAP UIDVALIDITY: as long as it stays the same, a UID always means the same message. When the server rebuilds
     * the folder it changes, and every cached message of the folder is stale.
     */
    public function uidValidity(): string;

    /** UIDs of the messages received on or after $since, oldest first. @return list<int> */
    public function uidsSince(\DateTimeInterface $since): array;

    /** Complete raw messages (headers + body). Missing UIDs are left out. @param list<int> $uids @return array<int, string> */
    public function fetchRaw(array $uids): array;

    /**
     * R5c: move a message to another folder of the account (registered → inProtocol, read → beenRead).
     * @throws \Throwable when the server refuses
     */
    public function move(int $uid, string $folder): void;
}
