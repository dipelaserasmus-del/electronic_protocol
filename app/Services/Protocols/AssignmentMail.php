<?php

namespace App\Services\Protocols;

use App\Models\Protocol;
use App\Models\User;
use App\Services\OutgoingMail;
use Carbon\Carbon;

/**
 * E-mail to a user a protocol was just assigned to (setting sendEmailOnDiekperaiosiChange), moved here from
 * ProtocolController (R1a). A sent e-mail is noted in the protocol's Παρατηρήσεις, as before.
 * Returns one plain-text line for the user who saved (the caller escapes it).
 */
final class AssignmentMail
{
    public function __construct(private OutgoingMail $mailer) {}

    public function send(Protocol $protocol, int $userId, string $type, ?User $by = null): ?string
    {
        $user = User::find($userId);
        if (! $user) {
            return null;
        }
        $name = $user->name;
        $sender = ($by ?? auth()->user())?->name;
        $date = $protocol->dateOf('protocoldate')?->format('d/m/Y');
        if ($type === 'd') {
            $diekp_eos = $protocol->dateOf('diekp_eos')?->format('d/m/Y') ?? '';
            $html = view('diekperaiosiMail', ['protocol' => $protocol, 'diekperaiotis' => $name, 'date' => $date, 'diekp_eos' => $diekp_eos, 'sender' => $sender])->render();
            $subject = 'Ανάθεση Πρωτοκόλλου για Διεκπεραίωση με θέμα "' . $protocol->thema . '"';
            [$what, $note] = ['ανάθεσης', 'διεκπεραίωσης'];
        } else {
            $html = view('diekpInformMail', ['protocol' => $protocol, 'diekperaiotis' => $name, 'date' => $date, 'sender' => $sender])->render();
            $subject = 'Ενημέρωση για Πρωτόκολλο με θέμα "' . $protocol->thema . '"';
            [$what, $note] = ['ενημέρωσης', 'ενημέρωσης'];
        }
        if (! $this->mailer->sendHtml($user->email, $subject, $html, 'intra-mail')) {
            return "Δεν ήταν δυνατή η αποστολή email $what στον $name.";
        }
        $stamp = Carbon::now()->format('d/m/Y H:i:s');
        $protocol->paratiriseis = ($protocol->paratiriseis ? $protocol->paratiriseis . ', ' : '') . "$stamp email $note σε $name";
        $protocol->save();

        return "Στάλθηκε email $what στον $name.";
    }
}
