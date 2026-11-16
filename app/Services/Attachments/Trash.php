<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\Config;
use App\Models\Protocol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * R4b: the attachments' trash ("Κάδος"). A trashed attachment keeps its file under trash/ for a number of days
 * (setting trashRetentionDays, default 30; 0 = until emptied by hand; files trashed before R4b count from the day it
 * was installed, trashRetentionStart), can be restored to its protocol, and is then deleted for good, by hand or by
 * the daily clean-up.
 *
 * Before R4b: the file was moved to trash/ but the row kept its arxeio/ path; a file already in trash/ under the same
 * name was deleted to make room; the attachments of a deleted protocol were deleted at once.
 */
final class Trash
{
    public const DEFAULT_DAYS = 30;

    public function retentionDays(): int
    {
        $v = Config::getConfigValueOf('trashRetentionDays');
        return $v === null || $v === '' ? self::DEFAULT_DAYS : max(0, (int) $v);
    }

    /** Move to the trash. $label: the protocol, when the protocol itself is being deleted. */
    public function trash(Attachment $a, ?User $by = null, ?string $label = null): void
    {
        if ($a->trashed()) {
            return;
        }
        $path = (string) $a->savedPath;
        if ($path !== '' && Storage::exists($path)) {
            $target = $this->free(preg_replace('~^arxeio/~', 'trash/', $path) === $path ? 'trash/' . $path : preg_replace('~^arxeio/~', 'trash/', $path));
            if (Storage::move($path, $target)) {   // a failed move leaves the file where it was, and the row says so
                $a->trashed_from = $path;
                $a->savedPath = $target;
            }
        }
        $a->protocol_label = $label ?? $a->protocol_label ?? $this->label($a->protocol);
        $a->deleted_by = $by?->id ?? auth()->id();
        $a->deleted_at = $a->freshTimestamp();
        $a->saveQuietly();   // the model's soft-delete event would set deleted_by from the session
    }

    /** Back to its protocol (which must still exist), at its old path or next to it if that name is taken now. */
    public function restore(Attachment $a): bool
    {
        if (! $a->trashed() || ! Protocol::whereKey($a->protocol_id)->exists()) {
            return false;
        }
        $current = $this->fileInTrash($a);
        $original = $a->trashed_from ?: (string) $a->savedPath;   // before R4b the row kept its old path
        if ($current !== null && $original !== '' && $current !== $original) {
            $original = $this->free($original);
            if (! Storage::move($current, $original)) {
                return false;
            }
            $a->savedPath = $original;
        }
        $a->trashed_from = null;
        $a->deleted_by = null;
        $a->deleted_at = null;
        $a->save();
        return true;
    }

    /** Delete for good: the file and the row. */
    public function purge(Attachment $a): void
    {
        $path = $a->trashed() ? $this->fileInTrash($a) : $a->storedFilePath();
        // never a file another (live) attachment points to (review of R4b: rows trashed before R4b can share a name)
        if ($path !== null && ! Attachment::where('savedPath', $path)->where('id', '!=', $a->id)->exists()) {
            Storage::delete($path);
        }
        $a->forceDelete();
    }

    /**
     * The file of a trashed attachment, only if it is in trash/. Rows trashed before R4b still say arxeio/…: their
     * file is looked for in trash/ only, never at the arxeio/ path, which may belong to a live attachment by now.
     */
    public function fileInTrash(Attachment $a): ?string
    {
        $path = (string) $a->savedPath;
        if (! str_starts_with($path, 'trash/')) {
            $path = preg_replace('~^arxeio/~', 'trash/', $path) ?? '';
        }
        return $path !== '' && str_starts_with($path, 'trash/') && Storage::exists($path) ? $path : null;
    }

    /** The daily clean-up: attachments in the trash for longer than the retention. Returns how many. */
    public function purgeExpired(?Carbon $now = null): int
    {
        $days = $this->retentionDays();
        if ($days === 0) {
            return 0;
        }
        $limit = ($now ?? Carbon::now())->copy()->subDays($days);
        $start = $this->start();
        if ($start && $start->greaterThanOrEqualTo($limit)) {
            return 0;   // everything in the trash counts from the start day, which is not $days ago yet
        }
        $count = 0;
        Attachment::onlyTrashed()->where('deleted_at', '<', $limit)->orderBy('id')->chunkById(200, function ($rows) use (&$count) {
            foreach ($rows as $a) {
                $this->purge($a);
                $count++;
            }
        });
        return $count;
    }

    /** When it will be deleted for good (null: kept until emptied by hand). */
    public function purgeDate(Attachment $a): ?Carbon
    {
        $days = $this->retentionDays();
        if ($days === 0 || ! $a->deleted_at) {
            return null;
        }
        $from = $a->deleted_at->copy();
        if (($start = $this->start()) && $start->greaterThan($from)) {
            $from = $start->copy();   // trashed before the retention existed: counted from its start
        }
        return $from->addDays($days);
    }

    /** The day the retention started (set by the R4b migration); files trashed before it count from it. */
    private function start(): ?Carbon
    {
        $v = Config::getConfigValueOf('trashRetentionStart');
        return $v ? Carbon::parse($v) : null;
    }

    public function label(?Protocol $p): ?string
    {
        if (! $p) {
            return null;
        }
        return mb_substr("{$p->protocolnum}/{$p->etos}" . ($p->thema ? " · {$p->thema}" : ''), 0, 255);
    }

    /** $path, or "name (2).ext" … when a file is already there (nothing is overwritten). */
    private function free(string $path): string
    {
        if (! Storage::exists($path)) {
            return $path;
        }
        $info = pathinfo($path);
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        for ($i = 2; ; $i++) {
            // the suffix must fit too: 255 bytes per name and FileNames::MAX_PATH_CHARS per path (review of R4b)
            $suffix = " ($i)" . $ext;
            $base = $info['filename'];
            while ($base !== '' && (strlen($base . $suffix) > \App\Support\FileNames::MAX_BYTES
                || mb_strlen($info['dirname'] . '/' . $base . $suffix) > \App\Support\FileNames::MAX_PATH_CHARS)) {
                $base = mb_substr($base, 0, -1);
            }
            $candidate = $info['dirname'] . '/' . $base . $suffix;
            if (! Storage::exists($candidate)) {
                return $candidate;
            }
        }
    }
}
