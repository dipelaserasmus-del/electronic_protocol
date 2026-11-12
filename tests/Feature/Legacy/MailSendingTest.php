<?php

namespace Tests\Feature\Legacy;

use App\Protocol;
use Tests\Feature\FeatureTestCase;

/**
 * KNOWN BUG (new task B09): all outgoing emails use the SwiftMailer API (setBody, Mail::failures)
 * removed in Laravel 9. On Laravel 13 every send throws → HTTP 500, although the protocol was saved.
 */
class MailSendingTest extends FeatureTestCase
{
    public function test_known_bug_receipt_email_on_save_returns_500_but_protocol_is_saved(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(\TypeError::class);   // Symfony Message::setBody() expects a Part, not a string

        try {
            $this->actingAs($this->writer())->post('/home', $this->protocolForm([
                'protocolnum' => 1, 'reply_to_email' => 'sender@example.test',
            ]));
        } finally {
            $this->assertSame(1, Protocol::count(), 'protocol is stored before the mail error');
        }
    }

    public function test_known_bug_send_receipt_button_fails(): void
    {
        $p = Protocol::factory()->number(1)->create();

        $this->actingAs($this->writer())
            ->post('/receiptToEmail', ['id' => $p->id, 'email' => 'sender@example.test'])
            ->assertStatus(500);
    }
}
