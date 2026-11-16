<?php

namespace App\Services\Mail;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Raw e-mails downloaded from the mail server, kept so the inbox page downloads each message only once (before
 * R5a every visit downloaded every message of the page in full: the main cause of the inbox timeouts).
 *
 * Location: storage/app/mail-cache/<mailbox>/<UIDVALIDITY>/<UID>.eml. Private storage, never under public/ (the old
 * per-user copies in public/tmp could be downloaded without logging in). A UID means the same message only within
 * one UIDVALIDITY, hence the extra level. Files are written under a temporary name and then renamed, so a reader
 * never sees half a message.
 */
final class MailCache
{
    private const ROOT = 'mail-cache';

    public function get(string $mailbox, string $uidValidity, int $uid): ?string
    {
        $path = $this->path($mailbox, $uidValidity, $uid);

        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    public function has(string $mailbox, string $uidValidity, int $uid): bool
    {
        return $this->disk()->exists($this->path($mailbox, $uidValidity, $uid));
    }

    public function put(string $mailbox, string $uidValidity, int $uid, string $raw): void
    {
        $path = $this->path($mailbox, $uidValidity, $uid);
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.part';
        if (! $this->disk()->put($tmp, $raw)) {
            throw new \RuntimeException("mail cache: cannot write $tmp");
        }
        if (! rename($this->disk()->path($tmp), $this->disk()->path($path))) {
            $this->disk()->delete($tmp);
            throw new \RuntimeException("mail cache: cannot rename $tmp");
        }
    }

    /**
     * Expiry: keep only the messages that are still in the folder ($uids) under the current UIDVALIDITY; messages
     * registered, moved or deleted meanwhile and every older UIDVALIDITY are removed. Run it under the mailbox's lock
     * (Inbox does). It never removes what another request may be writing or has just added: files being written
     * (".part", unless left over for an hour) and UIDs newer than the list it was given (new mail; UIDs only grow).
     *
     * @param list<int> $uids
     */
    public function keepOnly(string $mailbox, string $uidValidity, array $uids): void
    {
        $base = self::ROOT.'/'.$this->key($mailbox);
        foreach ($this->disk()->directories($base) as $dir) {
            if (basename($dir) !== $this->safe($uidValidity)) {
                $this->disk()->deleteDirectory($dir);
            }
        }
        $keep = array_flip($uids);
        $newest = $uids === [] ? 0 : max($uids);
        foreach ($this->disk()->files($base.'/'.$this->safe($uidValidity)) as $file) {
            $name = basename($file);
            if (str_ends_with($name, '.part')) {
                if ($this->disk()->lastModified($file) < time() - 3600) {
                    $this->disk()->delete($file);   // left by a request that died while writing
                }
                continue;
            }
            $uid = (int) basename($name, '.eml');
            if (! isset($keep[$uid]) && $uid <= $newest) {
                $this->disk()->delete($file);
            }
        }
    }

    /** Lock name for downloading into this mailbox's cache (one download at a time per mailbox). */
    public function lockName(string $mailbox): string
    {
        return 'mail-cache:'.$this->key($mailbox);
    }

    private function path(string $mailbox, string $uidValidity, int $uid): string
    {
        return self::ROOT.'/'.$this->key($mailbox).'/'.$this->safe($uidValidity).'/'.$uid.'.eml';
    }

    /** Readable but file-system safe: account and folder names may contain anything. */
    private function key(string $mailbox): string
    {
        return substr(preg_replace('/[^A-Za-z0-9._-]+/', '_', $mailbox), 0, 60).'-'.substr(sha1($mailbox), 0, 10);
    }

    private function safe(string $uidValidity): string
    {
        return preg_replace('/\D/', '', $uidValidity) ?: '0';
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
