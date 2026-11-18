<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * R6c: "Εκκαθάριση": attachments whose keeping time ("Διατήρηση", column expires) has passed. They go to the trash
 * (R4b), from where they are restored or deleted for good. Page: App\Livewire\ArchivePage; list to print: /expired.
 */
final class Expired
{
    public function __construct(private Trash $trash) {}

    /** Expired attachments, by folder, protocol number and id (the old print's order) */
    public function query(): Builder
    {
        return Attachment::query()
            ->whereNotNull('attachments.expires')->where('attachments.expires', '<', Carbon::today()->toDateString())
            ->leftJoin('protocols', 'protocols.id', '=', 'attachments.protocol_id')
            ->select('attachments.*')
            ->orderBy('protocols.fakelos')->orderBy('protocols.etos')->orderBy('protocols.protocolnum')->orderBy('attachments.id')
            ->with('protocol');
    }

    public function count(): int
    {
        return Attachment::whereNotNull('expires')->where('expires', '<', Carbon::today()->toDateString())->count();
    }

    /** @return int how many were moved to the trash */
    public function trashAll(User $by): int
    {
        $n = 0;
        Attachment::whereNotNull('expires')->where('expires', '<', Carbon::today()->toDateString())
            ->orderBy('id')->chunkById(200, function ($rows) use ($by, &$n) {
                foreach ($rows as $a) {
                    $this->trash->trash($a, $by);
                    $n++;
                }
            });

        return $n;
    }
}
