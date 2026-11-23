<?php

namespace App\Support\Mail;

use Webklex\PHPIMAP\Exceptions\AuthFailedException;

/**
 * R5m: why the inbox could not be opened, in words a user can act on. The organisation's mail server is often
 * overloaded (mornings above all), and the page must say that this is the server's delay, not the application's.
 *
 *   slow  — the server did not answer in time (connection or reply timed out): try again in a little while
 *   login — the server refused the username / password: an administrator checks the account
 *   other — anything else (refused, name not found, TLS): the reference code leads to the log
 */
final class MailboxFailure
{
    public const SLOW = 'slow';
    public const LOGIN = 'login';
    public const OTHER = 'other';

    /** Seconds the mail server is given (connection and each reply), config imap.accounts.<account>.timeout */
    public static function timeout(string $account): int
    {
        return max(1, (int) (config("imap.accounts.$account.timeout") ?? 30));
    }

    /**
     * @param float $seconds how long the attempt took: a reply that times out only says "empty response", so a
     *                       failure after (almost) the whole timeout counts as the server being slow
     */
    public static function kind(\Throwable $e, float $seconds, int $timeout): string
    {
        $messages = [];
        for ($x = $e; $x !== null; $x = $x->getPrevious()) {
            if ($x instanceof AuthFailedException) {
                return self::LOGIN;
            }
            $messages[] = $x->getMessage();
        }
        $text = implode(' | ', $messages);
        if (preg_match('/timed?\s*out|timeout/i', $text) || $seconds >= $timeout * 0.8) {
            return self::SLOW;
        }
        if (preg_match('/authenticat|login|credentials|password/i', $text)) {
            return self::LOGIN;
        }

        return self::OTHER;
    }
}
