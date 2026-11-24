<?php

namespace App\Http\Controllers;

use App\Services\Mail\Inbox;
use App\Support\Mail\MailContent;
use App\Support\Mail\MailParser;
use App\Support\SafeFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * R5c: the inbox (App\Livewire\EmailInbox) and the two things it opens in a new tab: an attachment and the HTML of an
 * e-mail (were in ProtocolController). Writers and administrators with inbox access (InboxAccess).
 *
 * Attachments and HTML are read from the mail cache, only for the messages of the inbox this user opened (R5a).
 */
class InboxController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth', new Middleware('writer:home/list'), 'email'];
    }

    public function show()
    {
        // copies left in public/tmp/u<id> by the old inbox (before R5a, of every user) could be opened without logging in
        foreach (\Illuminate\Support\Facades\Storage::disk('tmp')->directories() as $oldCopies) {
            if (preg_match('/^u\d+$/', $oldCopies)) {
                \Illuminate\Support\Facades\Storage::disk('tmp')->deleteDirectory($oldCopies);
            }
        }

        return view('inbox');
    }

    /** R5f: the number of e-mails in the inbox, for the top bar's badge (null: the mail server did not answer) */
    public function count(\App\Services\Mail\InboxCount $count)
    {
        return response()->json(['count' => $count->count()]);
    }

    /** The old address, with its page number (bookmarks) */
    public function old()
    {
        $page = (int) request('imap_page', 1);

        return redirect('/inbox'.($page > 1 ? "?page=$page" : ''), 301);
    }

    /** An attachment, with its name; opened in the browser only when safe (PDF, image, text), never run (R4a) */
    public function attachment(string $uid, string $key)
    {
        $mail = $this->mail($uid);
        $part = $mail ? MailContent::attachment($mail, $key) : null;
        abort_if($part === null, 404);

        return SafeFile::content($part['content'], $part['name'], $part['mime']);
    }

    /** R4c: an e-mail's attachment converted for the preview (same access as opening it) */
    public function preview(string $uid, string $key, \App\Services\Attachments\Preview $preview)
    {
        $mail = $this->mail($uid);
        $part = $mail ? MailContent::attachment($mail, $key) : null;
        abort_if($part === null, 404);

        return $preview->responseFor($part['content'], (string) $part['name']);
    }

    /**
     * R5a: the HTML of an e-mail, for the preview frame: logged-in inbox users only, in a sandbox (no scripts, no forms,
     * no access to the application). Before, it was written to public/tmp and ran on the application's origin.
     */
    public function html(string $uid)
    {
        $html = ($mail = $this->mail($uid)) ? MailContent::html($mail) : null;
        abort_if($html === null, 404);
        // the content is UTF-8 now: replace the charset the e-mail declared
        $html = preg_replace('/charset=[\s\S]+?"/', 'charset=utf-8"', $html);

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            // no allow-scripts: same origin only lets the inbox page size the frame; links open in a new tab
            ->header('Content-Security-Policy', "sandbox allow-same-origin allow-popups allow-popups-to-escape-sandbox; script-src 'none'; object-src 'none'")
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /** A message of the inbox this user opened (session "inbox"), from the cache; null when it is not there. */
    private function mail(string $uid): ?\ZBateson\MailMimeParser\Message
    {
        $inbox = session('inbox');
        if (! is_array($inbox) || ! ctype_digit($uid)) {
            return null;
        }
        $raw = app(Inbox::class)->raw($inbox['mailbox'], $inbox['uidValidity'], (int) $uid);

        return $raw === null ? null : MailParser::parse($raw);
    }
}
