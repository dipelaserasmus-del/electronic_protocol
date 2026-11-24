<?php

namespace App\Console\Commands;

use App\Services\Attachments\Trash;
use Illuminate\Console\Command;

/** R4b: delete for good the attachments that have been in the trash longer than the retention (Ρυθμίσεις). */
class PurgeTrash extends Command
{
    protected $signature = 'attachments:purge-trash';

    protected $description = 'Οριστική διαγραφή συνημμένων που είναι στον κάδο περισσότερες ημέρες από όσες ορίζουν οι ρυθμίσεις';

    public function handle(Trash $trash, \App\Services\Protocols\ProtocolTrash $protocols): int
    {
        $days = $trash->retentionDays();
        if ($days === 0) {
            $this->info('Ο κάδος κρατά τα αρχεία μέχρι να αδειάσει με το χέρι (ρύθμιση 0 ημέρες).');
            return self::SUCCESS;
        }
        $p = $protocols->purgeExpired();   // R4h
        $this->info('Διαγράφηκαν οριστικά: ' . $trash->purgeExpired() . " αρχεία και $p πρωτόκολλα (στον κάδο πάνω από $days ημέρες).");
        return self::SUCCESS;
    }
}
