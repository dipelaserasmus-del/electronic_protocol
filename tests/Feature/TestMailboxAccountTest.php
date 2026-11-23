<?php

namespace Tests\Feature;

use App\Livewire\SettingsPage;
use App\Models\Config;
use App\Support\SettingsCatalog;
use Livewire\Livewire;

/**
 * L03: the optional test mailbox of the local real-data copy. config/imap.php adds the account «test» only when
 * TEST_IMAP_HOST is set (local-data/test-mailbox.env through docker-compose.real.yml); Ρυθμίσεις then offer it.
 */
class TestMailboxAccountTest extends FeatureTestCase
{
    private const VARS = ['TEST_IMAP_HOST', 'TEST_IMAP_PORT', 'TEST_IMAP_ENCRYPTION', 'TEST_IMAP_VALIDATE_CERT',
        'TEST_IMAP_USERNAME', 'TEST_IMAP_PASSWORD'];

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
            'validate_cert' => true, 'username' => 'demo@example.org', 'password' => 'invented-secret',
        ], $accounts['test']);
        // the default (mock) account is unchanged: the test account never replaces it
        $this->assertSame(array_intersect_key(config('imap.accounts.default'), $accounts['default']), $accounts['default']);

        $this->environment(['TEST_IMAP_HOST' => 'mail.example.org', 'TEST_IMAP_PORT' => '143',
            'TEST_IMAP_ENCRYPTION' => 'tls', 'TEST_IMAP_VALIDATE_CERT' => 'false']);
        $test = $this->imapConfig()['accounts']['test'];
        $this->assertSame(['Δοκιμαστικός λογαριασμός', '143', 'tls', false],
            [$test['label'], $test['port'], $test['encryption'], $test['validate_cert']]);
    }

    public function test_settings_offer_the_test_account_by_its_label_and_save_it(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(SettingsPage::class)->assertDontSee('Δοκιμαστικός λογαριασμός');

        config(['imap.accounts.test' => ['label' => 'Δοκιμαστικός λογαριασμός (demo@example.org)',
            'host' => 'imap.example.org', 'username' => 'demo@example.org', 'password' => 'x']]);
        $this->assertSame('Δοκιμαστικός λογαριασμός (demo@example.org)', SettingsCatalog::imapAccounts()['test']);
        $this->assertSame('default', SettingsCatalog::imapAccounts()['default']);

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->assertSee('<option value="test"', false)->assertSee('Δοκιμαστικός λογαριασμός (demo@example.org)')
            ->set('values.defaultImapEmail', 'test')->call('save')->assertHasNoErrors();
        $this->assertSame('test', Config::getConfigValueOf('defaultImapEmail'));
    }

    public function test_an_unknown_account_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(SettingsPage::class)
            ->set('values.defaultImapEmail', 'test')->call('save')->assertHasErrors('values.defaultImapEmail');
        $this->assertNotSame('test', Config::getConfigValueOf('defaultImapEmail'));
    }
}
