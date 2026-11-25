<?php

namespace Tests\Feature;

use App\Livewire\SettingsPage;
use App\Models\Config;
use App\Services\OutgoingMail;
use App\Support\Mail\TestMailRedirect;
use App\Support\SettingsCatalog;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * L03: the optional test mailbox of the local real-data copy. config/imap.php adds the account «test» only when
 * TEST_IMAP_HOST is set (local-data/test-mailbox.env through docker-compose.real.yml); Ρυθμίσεις then offer it.
 */
class TestMailboxAccountTest extends FeatureTestCase
{
    private const VARS = ['TEST_IMAP_HOST', 'TEST_IMAP_PORT', 'TEST_IMAP_ENCRYPTION', 'TEST_IMAP_VALIDATE_CERT',
        'TEST_IMAP_USERNAME', 'TEST_IMAP_PASSWORD', 'TEST_SMTP_HOST', 'TEST_SMTP_PORT', 'TEST_SMTP_USERNAME',
        'TEST_SMTP_PASSWORD', 'TEST_SMTP_FROM', 'TEST_MAIL_ONLY_TO'];

    protected function tearDown(): void
    {
        $this->environment([]);
        parent::tearDown();
    }

    /** @param array<string, string> $vars */
    private function environment(array $vars): void
    {
        foreach (self::VARS as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
        foreach ($vars as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }
    }

    /** @return array<string, mixed> config/imap.php as it reads the environment now */
    private function imapConfig(): array
    {
        return require config_path('imap.php');
    }

    public function test_no_test_account_without_a_host(): void
    {
        $this->environment([]);
        $this->assertSame(['default', 'gmail', 'sch'], array_keys($this->imapConfig()['accounts']));

        $this->environment(['TEST_IMAP_HOST' => '', 'TEST_IMAP_USERNAME' => 'demo@example.org']);
        $this->assertArrayNotHasKey('test', $this->imapConfig()['accounts']);
    }

    public function test_the_test_account_reads_its_own_variables(): void
    {
        $this->environment(['TEST_IMAP_HOST' => 'imap.example.org', 'TEST_IMAP_USERNAME' => 'demo@example.org',
            'TEST_IMAP_PASSWORD' => 'invented-secret']);
        $accounts = $this->imapConfig()['accounts'];

        $this->assertSame(['default', 'test', 'gmail', 'sch'], array_keys($accounts));
        $this->assertSame([
            'label' => 'Δοκιμαστικός λογαριασμός (demo@example.org)',
            'host' => 'imap.example.org', 'port' => 993, 'protocol' => 'imap', 'encryption' => 'ssl',
            'validate_cert' => true, 'username' => 'demo@example.org', 'password' => 'invented-secret', 'timeout' => 30,
        ], $accounts['test']);
        // the default (mock) account is unchanged: the test account never replaces it
        $this->assertSame(array_intersect_key(config('imap.accounts.default'), $accounts['default']), $accounts['default']);

        $this->environment(['TEST_IMAP_HOST' => 'mail.example.org', 'TEST_IMAP_PORT' => '143',
            'TEST_IMAP_ENCRYPTION' => 'tls', 'TEST_IMAP_VALIDATE_CERT' => 'false']);
        $test = $this->imapConfig()['accounts']['test'];
        // the old app's "tls" = STARTTLS (production .env: 143 + tls); this library's "tls" would be TLS from the start
        $this->assertSame(['Δοκιμαστικός λογαριασμός', '143', 'starttls', false],
            [$test['label'], $test['port'], $test['encryption'], $test['validate_cert']]);
        $this->environment(['TEST_IMAP_HOST' => 'mail.example.org', 'TEST_IMAP_PORT' => '993', 'TEST_IMAP_ENCRYPTION' => 'tls']);
        $this->assertSame('tls', $this->imapConfig()['accounts']['test']['encryption']);
    }

    /** production's .env (old app, ext-imap): IMAP_PORT=143 + IMAP_ENCRYPTION=tls must keep working after the migration */
    public function test_the_default_account_reads_tls_on_143_as_starttls(): void
    {
        $original = [$_SERVER['IMAP_PORT'] ?? null, $_SERVER['IMAP_ENCRYPTION'] ?? null];
        try {
            foreach ([['143', 'tls', 'starttls'], ['993', 'ssl', 'ssl'], ['143', 'false', false], ['993', 'tls', 'tls']] as [$port, $enc, $want]) {
                $_SERVER['IMAP_PORT'] = $_ENV['IMAP_PORT'] = $port;
                $_SERVER['IMAP_ENCRYPTION'] = $_ENV['IMAP_ENCRYPTION'] = $enc;
                $this->assertSame($want, $this->imapConfig()['accounts']['default']['encryption'], "$port + $enc");
            }
        } finally {
            foreach (['IMAP_PORT' => $original[0], 'IMAP_ENCRYPTION' => $original[1]] as $name => $value) {
                if ($value === null) {
                    unset($_SERVER[$name], $_ENV[$name]);
                } else {
                    $_SERVER[$name] = $_ENV[$name] = $value;
                }
            }
        }
    }

    public function test_settings_offer_the_test_account_by_its_label_and_save_it(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(SettingsPage::class)->call('show', 'mailbox')->assertSee('id="s-defaultImapEmail"', false)
            ->assertDontSee('Δοκιμαστικός λογαριασμός');   // R6f: on the account's own section

        config(['imap.accounts.test' => ['label' => 'Δοκιμαστικός λογαριασμός (demo@example.org)',
            'host' => 'imap.example.org', 'username' => 'demo@example.org', 'password' => 'x']]);
        $this->assertSame('Δοκιμαστικός λογαριασμός (demo@example.org)', SettingsCatalog::imapAccounts()['test']);
        $this->assertSame('default', SettingsCatalog::imapAccounts()['default']);

        Livewire::actingAs($admin)->test(SettingsPage::class)->call('show', 'mailbox')
            ->assertSee('<option value="test"', false)->assertSee('Δοκιμαστικός λογαριασμός (demo@example.org)')
            ->set('values.defaultImapEmail', 'test')->call('save')->assertHasNoErrors();
        $this->assertSame('test', Config::getConfigValueOf('defaultImapEmail'));
    }

    public function test_an_unknown_account_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(SettingsPage::class)->call('show', 'mailbox')
            ->set('values.defaultImapEmail', 'test')->call('save')->assertHasErrors('values.defaultImapEmail');
        $this->assertNotSame('test', Config::getConfigValueOf('defaultImapEmail'));
    }

    /** @return array<string, mixed> config/mail.php as it reads the environment now */
    private function mailConfig(): array
    {
        return require config_path('mail.php');
    }

    public function test_without_a_test_smtp_host_mail_settings_are_unchanged(): void
    {
        $this->environment(['TEST_IMAP_HOST' => 'imap.example.org', 'TEST_IMAP_USERNAME' => 'demo@example.org']);
        $mail = $this->mailConfig();
        $this->assertFalse($mail['test']['enabled']);
        $this->assertSame(env('MAIL_HOST', 'smtp.mailgun.org'), $mail['mailers']['smtp']['host']);
        $this->assertSame($mail['mailers']['smtp'], $mail['mailers']['intra-mail']);
        $this->assertSame(env('MAIL_FROM_ADDRESS', 'hello@example.com'), $mail['from']['address']);
    }

    public function test_the_test_smtp_reuses_the_imap_login_and_sends_only_to_the_test_mailbox(): void
    {
        $this->environment(['TEST_IMAP_HOST' => 'imap.example.org', 'TEST_IMAP_USERNAME' => 'demo@example.org',
            'TEST_IMAP_PASSWORD' => 'invented-secret', 'TEST_SMTP_HOST' => 'smtp.example.org', 'TEST_SMTP_PORT' => '465',
            'TEST_SMTP_USERNAME' => '', 'TEST_MAIL_ONLY_TO' => '']);
        $mail = $this->mailConfig();
        $this->assertTrue($mail['test']['enabled']);
        foreach (['smtp', 'intra-mail'] as $mailer) {
            $this->assertSame(['smtp.example.org', 465, 'demo@example.org', 'invented-secret'], [$mail['mailers'][$mailer]['host'],
                $mail['mailers'][$mailer]['port'], $mail['mailers'][$mailer]['username'], $mail['mailers'][$mailer]['password']]);
        }
        $this->assertSame('demo@example.org', $mail['from']['address']);
        $this->assertSame(['demo@example.org'], $mail['test']['only_to']);

        $this->environment(['TEST_SMTP_HOST' => 'smtp.example.org', 'TEST_SMTP_USERNAME' => 'sender@example.org',
            'TEST_SMTP_FROM' => 'from@example.org', 'TEST_MAIL_ONLY_TO' => ' a@example.org, b@example.org ,']);
        $mail = $this->mailConfig();
        $this->assertSame([587, 'from@example.org'], [$mail['mailers']['smtp']['port'], $mail['from']['address']]);
        $this->assertSame(['a@example.org', 'b@example.org'], $mail['test']['only_to']);
    }

    /** @return list<\Symfony\Component\Mime\Email> */
    private function sent(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages()));
    }

    /** @param list<\Symfony\Component\Mime\Address> $addresses @return list<string> */
    private function addresses(array $addresses): array
    {
        return array_map(fn ($a) => $a->getAddress(), $addresses);
    }

    public function test_real_recipients_are_taken_off_and_the_mail_goes_to_the_test_mailbox(): void
    {
        config(['mail.test' => ['enabled' => true, 'only_to' => ['Demo@Example.org', 'other@example.org']]]);
        $out = app(OutgoingMail::class);

        $this->assertTrue($out->sendHtml('staff@example.org', 'Ανάθεση πρωτοκόλλου 12', '<p>x</p>'));
        $this->assertTrue($out->sendHtml('other@example.org', 'Απόδειξη', '<p>y</p>'));
        Mail::mailer('array')->raw('z', fn ($m) => $m->to(['staff@example.org', 'demo@example.org'])
            ->cc('boss@example.org')->bcc('other@example.org')->subject('Πολλοί'));

        [$redirected, $allowed, $mixed] = $this->sent();
        $this->assertSame(['demo@example.org'], $this->addresses($redirected->getTo()));
        $this->assertSame('[ΔΟΚΙΜΗ] προς: staff@example.org · Ανάθεση πρωτοκόλλου 12', $redirected->getSubject());
        $this->assertSame('staff@example.org', $redirected->getHeaders()->get('X-Test-Original-Recipients')->getBodyAsString());

        $this->assertSame(['other@example.org'], $this->addresses($allowed->getTo()));
        $this->assertSame('Απόδειξη', $allowed->getSubject());

        $this->assertSame(['demo@example.org'], $this->addresses($mixed->getTo()));
        $this->assertSame([], $mixed->getCc());
        $this->assertSame(['other@example.org'], $this->addresses($mixed->getBcc()));
        $this->assertStringStartsWith(TestMailRedirect::SUBJECT_PREFIX.' προς: staff@example.org, boss@example.org · ', $mixed->getSubject());
    }

    public function test_no_allowed_address_means_nothing_is_sent_and_off_means_untouched(): void
    {
        config(['mail.test' => ['enabled' => true, 'only_to' => []]]);
        app(OutgoingMail::class)->sendHtml('staff@example.org', 'Θέμα', '<p>x</p>');
        $this->assertSame([], $this->sent());

        config(['mail.test' => ['enabled' => false, 'only_to' => []]]);
        app(OutgoingMail::class)->sendHtml('staff@example.org', 'Θέμα', '<p>x</p>');
        $this->assertSame(['staff@example.org'], $this->addresses($this->sent()[0]->getTo()));
        $this->assertSame('Θέμα', $this->sent()[0]->getSubject());
    }
}
