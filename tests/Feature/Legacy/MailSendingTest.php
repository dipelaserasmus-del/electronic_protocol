<?php

namespace Tests\Feature\Legacy;

use App\Protocol;
use Tests\Feature\FeatureTestCase;

/**
 * B09: outgoing e-mail (was: every send crashed with HTTP 500 — old SwiftMailer API).
 * A failed send must never break the user's action.
 */
class MailSendingTest extends FeatureTestCase
{
    private function subjectAndTo($sent): array
    {
        $email = $sent->getOriginalMessage();
        return [$email->getSubject(), $email->getTo()[0]->getAddress()];
    }

    /** was: known_bug_receipt_email_on_save_returns_500_but_protocol_is_saved */
    public function test_receipt_email_is_sent_when_saving_a_protocol(): void
    {
        $response = $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'reply_to_email' => 'sender@example.test',
        ]));

        $p = Protocol::firstOrFail();
        $response->assertRedirect("home/{$p->id}");
        $this->assertCount(1, $this->sentMails());
        $this->assertSame(['Καταχώριση στο Ηλεκτρονικό Πρωτόκολλο.', 'sender@example.test'], $this->subjectAndTo($this->sentMails()->first()));
        $this->assertStringContainsString('απόδειξη παραλαβής σε sender@example.test', $p->paratiriseis);
    }

    /** was: known_bug_send_receipt_button_fails */
    public function test_send_receipt_button_sends_the_email(): void
    {
        $p = Protocol::factory()->number(1)->create();

        $this->actingAs($this->writer())
            ->post('/receiptToEmail', ['id' => $p->id, 'email' => 'sender@example.test'])
            ->assertOk();

        $this->assertCount(1, $this->sentMails());
        $this->assertStringContainsString('απόδειξη παραλαβής', $p->fresh()->paratiriseis);
    }

    public function test_assignment_notifies_the_assignees_through_the_internal_mailer(): void
    {
        $writer = $this->writer();
        $reader = $this->reader();
        $p = Protocol::factory()->number(1)->create(['thema' => 'Θέμα ανάθεσης']);

        $this->actingAs($this->assigner())->post('/anathesiSe', [
            'id' => $p->id, 'diekperaiosi' => "d{$writer->id},e{$reader->id}", 'diekp_eos' => '',
        ])->assertSuccessful();

        $sent = $this->sentMails('intra-mail');
        $this->assertCount(2, $sent);
        $this->assertSame(['Ανάθεση Πρωτοκόλλου για Διεκπεραίωση με θέμα "Θέμα ανάθεσης"', $writer->email], $this->subjectAndTo($sent[0]));
        $this->assertSame(['Ενημέρωση για Πρωτόκολλο με θέμα "Θέμα ανάθεσης"', $reader->email], $this->subjectAndTo($sent[1]));
    }

    public function test_unreachable_mail_server_does_not_break_saving(): void
    {
        // a mail server that refuses connections immediately
        config(['mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2], 'mail.default' => 'smtp']);

        $response = $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'reply_to_email' => 'sender@example.test',
        ]));

        $p = Protocol::firstOrFail();
        $response->assertRedirect("home/{$p->id}");
        $this->assertStringContainsString('Μη δυνατή αποστολή βεβαίωσης καταχώρισης με email', session('notification')['message']);
        $this->assertStringNotContainsString('απόδειξη παραλαβής', (string) $p->paratiriseis);
    }

    public function test_unreachable_mail_server_on_receipt_button_returns_clear_error(): void
    {
        config(['mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2], 'mail.default' => 'smtp']);
        $p = Protocol::factory()->number(1)->create();

        $this->actingAs($this->writer())
            ->post('/receiptToEmail', ['id' => $p->id, 'email' => 'sender@example.test'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'Αποτυχία αποστολής email. Η αιτία καταγράφηκε στο αρχείο καταγραφής.');
    }
}
