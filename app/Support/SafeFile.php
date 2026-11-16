<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * R4a: files in and out (S06).
 *
 * Out: a stored attachment opens in the browser only when it is a PDF, an image or plain text, and then with the
 * type its extension says, never the type stored at upload (an uploaded .html or .svg used to open as a page of the
 * application and could run script with the user's session). Everything else downloads. The file name is encoded
 * properly (Greek, quotes, ;); the browser may not guess another type (nosniff) or run anything (sandbox).
 *
 * In: uploads larger than config attachments.max_kb, or of a type that runs code (attachments.blocked_extensions,
 * checked on the name and on the content), are refused with a message.
 */
final class SafeFile
{
    /** Response for a file on disk (streamed, not read into memory). */
    public static function download(string $absolutePath, ?string $name, ?string $storedMime = null): BinaryFileResponse
    {
        $name = self::displayName($name, $absolutePath);
        [$disposition, $type] = self::kind($name, $storedMime);
        $response = new BinaryFileResponse($absolutePath, 200, [], false);
        $response->headers->set('Content-Type', $type);
        $response->headers->set('Content-Disposition', self::disposition($disposition, $name));
        return self::harden($response);
    }

    /** Response for content in memory (e.g. an e-mail's attachment). */
    public static function content(string $content, ?string $name, ?string $storedMime = null): Response
    {
        $name = self::displayName($name);
        [$disposition, $type] = self::kind($name, $storedMime);
        $response = new Response($content, 200, ['Content-Type' => $type]);
        $response->headers->set('Content-Disposition', self::disposition($disposition, $name));
        return self::harden($response);
    }

    /** ['inline'|'attachment', Content-Type] */
    public static function kind(string $name, ?string $storedMime): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $inline = config('attachments.inline', []);
        if (isset($inline[$ext])) {
            return ['inline', $inline[$ext]];
        }
        $mime = strtolower(trim((string) $storedMime));
        // whole types only ("officedocument" types contain "xml" and are fine)
        $risky = preg_match('~^(text/html|application/xhtml\+xml|image/svg\+xml|text/xml|application/xml|text/javascript|application/(x-)?(java|ecma)script|application/x-(httpd-)?php|application/x-sh)$~', $mime);
        return ['attachment', ! $risky && preg_match('~^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$~', $mime) ? $mime : 'application/octet-stream'];
    }

    /** null when the upload may be stored, otherwise the message for the user */
    public static function refuse(UploadedFile $file): ?string
    {
        $name = (string) $file->getClientOriginalName();
        $maxKb = (int) config('attachments.max_kb', 65536);
        if (! $file->isValid()) {
            return "Το «{$name}» δεν ανέβηκε σωστά. Δοκιμάστε ξανά.";
        }
        if ($file->getSize() > $maxKb * 1024) {
            return "Το «{$name}» είναι μεγαλύτερο από " . FileBadge::size($maxKb * 1024) . '.';
        }
        $blocked = config('attachments.blocked_extensions', []);
        $parts = array_map('strtolower', array_slice(explode('.', $name), 1));   // every extension: "a.pdf.exe"
        // the content too: a program or a web page renamed to .pdf
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
        if (array_intersect($parts, $blocked) || preg_match('~^(application/x-(dosexec|msdownload|executable|sharedlib|mach-binary|elf|pie-executable)|text/html|image/svg)~', $sniffed)) {
            return "Το «{$name}» δεν επιτρέπεται: είδος αρχείου που μπορεί να εκτελέσει κώδικα.";
        }
        return null;
    }

    private static function displayName(?string $name, ?string $path = null): string
    {
        $name = str_replace(['/', '\\', "\r", "\n", "\0"], '_', trim((string) $name));
        return $name !== '' ? $name : ($path ? basename($path) : 'αρχείο');
    }

    private static function disposition(string $type, string $name): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]|["\\\\%\/]/', '_', $name) ?: 'file';
        return HeaderUtils::makeDisposition($type, $name, $fallback);
    }

    private static function harden(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        // PDFs and raster images can't run script in the application; Chrome refuses to show a sandboxed PDF.
        // Everything else (text, downloads) gets the sandbox, in case a browser renders it anyway.
        if (! preg_match('~^(application/pdf|image/(png|jpeg|gif|webp))$~', (string) $response->headers->get('Content-Type'))) {
            $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'");
        }
        return $response;
    }
}
