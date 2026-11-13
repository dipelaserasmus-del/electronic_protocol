<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * B09: all outgoing e-mail goes through here.
 *
 * The old code used the SwiftMailer API ($message->setBody(), Mail::failures()) removed in Laravel 9,
 * so every send crashed with HTTP 500 after the protocol had already been saved.
 * A failed send must never break the user's action: it is logged and reported as `false`.
 */
class OutgoingMail
{
    /** Last error message (for the user notification), null after a successful send. */
    public ?string $lastError = null;

    /**
     * @param string|null $mailer  null = default mailer; 'intra-mail' = mailer for internal notifications
     */
    public function sendHtml(string $to, string $subject, string $html, ?string $mailer = null): bool
    {
        $this->lastError = null;
        try {
            Mail::mailer($mailer)->html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
                if ($from = config('mail.from.address')) {
                    $message->from($from, config('mail.from.name'));
                }
            });
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Outgoing e-mail failed', ['to' => $to, 'subject' => $subject, 'mailer' => $mailer, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
