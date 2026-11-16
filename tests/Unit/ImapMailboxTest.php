<?php

namespace Tests\Unit;

use App\Services\Mail\ImapMailbox;
use PHPUnit\Framework\TestCase;

/**
 * R5a: the IMAP server returns header and body separately, and the header without the empty line that ends it.
 * Joined without that line, the body was read as more headers (found by the browser test with the real mock server).
 */
class ImapMailboxTest extends TestCase
{
    public function test_header_and_body_are_joined_with_exactly_one_empty_line(): void
    {
        $expected = "From: a@example.org\r\nSubject: x\r\n\r\nBody\r\n";
        foreach (["From: a@example.org\r\nSubject: x", "From: a@example.org\r\nSubject: x\r\n", "From: a@example.org\r\nSubject: x\r\n\r\n"] as $header) {
            $this->assertSame($expected, ImapMailbox::join($header, "Body\r\n"), json_encode($header));
        }
    }
}
