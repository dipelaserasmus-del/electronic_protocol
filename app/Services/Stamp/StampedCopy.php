<?php

namespace App\Services\Stamp;

use App\Models\Attachment;
use App\Models\Config;
use App\Models\User;
use App\Services\Protocols\Stamp;
use App\Support\UserPreferences;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * R4f: the stamp is put when a file is viewed, downloaded or printed, not when it is saved. The stored file stays as it
 * came; the stamped copy is made on first use and kept in storage/app/stamped/<id>-<key>.<ext>, where the key changes
 * with what the stamp shows (the template, the protocol's number and date) and with the file itself, so a stale copy
 * is never served. Each user chooses «Με σφραγίδα» or the original (preference "stamped"); R4i (owner, 2026-11-25):
 * every user may, whatever the setting "putStamp", which is only the choice of users who have not chosen.
 * Files saved before R4f carry the old stamp inside them (attachments.stamp_in_file): never stamped again.
 */
final class StampedCopy
{
    public const DIR = 'stamped';

    public const PREFERENCE = 'stamped';

    /**
     * R4n: where the stamp is on this file, as one of Stamp::PLACES: its own choice, otherwise the stamp's place when that
     * is one of them (a stamp at the bottom: none of the three)
     */
    public static function place(Attachment $a): ?string
    {
        $own = $a->stamp_place;
        if (isset(Stamp::PLACES[$own ?? ''])) {
            return $own;
        }
        $corner = StampTemplate::current()->corner;
        return isset(Stamp::PLACES[$corner]) ? $corner : null;
    }

    /** this attachment can be stamped when shown (a protocol's file of a type that takes a stamp, none inside it already) */
    public static function applies(Attachment $a): bool
    {
        $ext = strtolower(pathinfo((string) ($a->savedPath ?: $a->name), PATHINFO_EXTENSION));
        return ! $a->stamp_in_file && $a->protocol_id
            && in_array($ext, Stamp::STAMPABLE, true);
    }

    /** the user wants files with the stamp: their own choice, otherwise the setting "putStamp" (R4i) */
    public static function wanted(?User $user): bool
    {
        $own = $user?->preferences[self::PREFERENCE] ?? null;
        return UserPreferences::valid(self::PREFERENCE, $own) ? $own === 'yes' : (bool) Config::getConfigValueOf('putStamp');
    }

    /**
     * The file to serve for this user: the stamped copy when it applies and is wanted (unless $original), otherwise
     * the stored file. Null when the stored file is missing.
     */
    public function pathFor(Attachment $a, ?User $user, bool $original = false): ?string
    {
        $stored = $a->storedFilePath();
        if ($stored === null || $original || ! self::applies($a) || ! self::wanted($user)) {
            return $stored;
        }
        return $this->copy($a, $stored) ?? $stored;
    }

    /** the stamped copy (made now if needed), or null when it cannot be made */
    public function copy(Attachment $a, ?string $stored = null): ?string
    {
        $stored ??= $a->storedFilePath();
        $protocol = $a->protocol;
        if ($stored === null || $protocol === null) {
            return null;
        }
        $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
        $path = self::DIR . '/' . $a->id . '-' . $this->key($a, $stored) . '.' . $ext;
        if (Storage::exists($path)) {
            return $path;
        }
        return Cache::lock('stamped-copy-' . $a->id, 30)->block(20, function () use ($a, $stored, $protocol, $path) {
            if (Storage::exists($path)) {
                return $path;   // made meanwhile by another request
            }
            self::forget($a);
            $work = preg_replace('/(\.[^.\/]+)$/', '.part$1', $path);   // keeps the extension: the stamp goes by it
            Storage::copy($stored, $work);
            $stamp = new Stamp();
            try {
                if ($stamp->prepare($protocol)) {
                    $stamp->placeAt($a->stamp_place);   // R4n: this file's own place, if a user chose one
                    $stamp->apply($work);
                }
            } finally {
                $stamp->cleanup();
            }
            Storage::move($work, $path);
            return $path;
        });
    }

    /** removes the stamped copies of an attachment (deleted, or about to be made again) */
    public static function forget(Attachment $a): void
    {
        if ($a->id === null) {
            return;
        }
        foreach (glob(Storage::path(self::DIR) . '/' . (int) $a->id . '-*') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** what the stamped copy depends on */
    private function key(Attachment $a, string $stored): string
    {
        $p = $a->protocol;
        $file = $a->sha256 ?: (Storage::size($stored) . ':' . Storage::lastModified($stored));
        // the values the stamp shows (number, date, year, the office's name: review of R4f), the template, the file, and
        // how the stamp is drawn (review of R4k: copies drawn before the white under the number are made again)
        $values = json_encode(StampTemplate::valuesFor($p), JSON_UNESCAPED_UNICODE);
        return substr(sha1(implode('|', [StampTemplate::current()->hash(), $values, $stored, $file, 'r'.StampRenderer::REVISION,
            'p'.$a->stamp_place])), 0, 16);   // R4n: this file's own place
    }
}
