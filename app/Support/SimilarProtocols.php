<?php

namespace App\Support;

use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\Protocols\ProtocolWriter;
use Carbon\Carbon;

/**
 * Protocols that may be the same document registered before (the old YES/NO dialogs, now notices): same subject, or
 * same incoming number and date. Only protocols the user may see; at most five, newest first. Used by the protocol
 * form (R1b) and the inbox (R5d).
 */
final class SimilarProtocols
{
    /** @return list<Protocol> */
    public static function sameSubject(User $user, ?string $thema, ?int $except = null): array
    {
        $thema = trim((string) $thema);
        if ($thema === '' || ProtocolWriter::isPlaceholder(new Protocol(['thema' => $thema]))) {
            return [];
        }

        return self::take($user, Protocol::query()->where('thema', $thema), $except);
    }

    /** @return list<Protocol> */
    public static function sameIncoming(User $user, ?string $inNum, ?string $inDate, ?int $except = null): array
    {
        $date = DateInput::expand((string) $inDate);
        if (trim((string) $inNum) === '' || ! $date) {
            return [];
        }
        $query = Protocol::query()->where('in_num', trim((string) $inNum))
            ->where('in_date', Carbon::createFromFormat('!'.DateInput::FORMAT, $date)->format('Y-m-d'));

        return self::take($user, $query, $except);
    }

    private static function take(User $user, $query, ?int $except): array
    {
        $query->when($except, fn ($q) => $q->where('id', '!=', $except));
        if (ProtocolPolicy::isLimited($user)) {
            $query->ownOrAssigned((int) $user->id);
        }

        return $query->orderByDesc('etos')->orderByDesc('protocolnum')->take(5)->get(['id', 'protocolnum', 'etos'])->all();
    }
}
