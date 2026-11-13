<?php

namespace Tests\Feature;

use App\Config;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Base for HTTP-level tests: fresh MySQL schema + the standard seed (roles, settings, folders),
 * fake file storage, no outgoing network calls.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // all mailers write to memory (the internal-notification mailer is SMTP in real use)
        config(['mail.default' => 'array', 'mail.mailers.intra-mail' => ['transport' => 'array']]);
        // the "check for updates" call goes to GitHub on page load; never in tests
        Config::setConfigValueOf('updatesAutoCheck', 0);
        // AppServiceProvider shares settings with all views at boot; on a brand-new test database the
        // tables didn't exist yet at that moment, so share them again now that they are seeded.
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        // the dev stack's storage folder is shared with the tests: keep its registration marker
        $marker = storage_path('conf/.denyregister');
        $this->savedMarker = file_exists($marker) ? file_get_contents($marker) : null;
        @unlink($marker);
    }

    private ?string $savedMarker = null;

    protected function tearDown(): void
    {
        $marker = storage_path('conf/.denyregister');
        @unlink($marker);
        if ($this->savedMarker !== null) {
            file_put_contents($marker, $this->savedMarker);
        }
        parent::tearDown();
    }

    /** Messages "sent" through a mailer configured with the array transport */
    protected function sentMails(?string $mailer = null): \Illuminate\Support\Collection
    {
        return app('mail.manager')->mailer($mailer)->getSymfonyTransport()->messages();
    }

    protected function admin(): User { return User::factory()->admin()->create(); }
    protected function assigner(): User { return User::factory()->assigner()->create(); }
    protected function writer(): User { return User::factory()->writer()->create(); }
    protected function reader(): User { return User::factory()->reader()->create(); }

    protected function setting(string $key, mixed $value): void
    {
        Config::setConfigValueOf($key, $value);
    }

    /** All fields the protocol form posts, with empty defaults (the legacy controller reads them unconditionally). */
    protected function protocolForm(array $overrides = []): array
    {
        return array_merge([
            'protocolnum' => '',
            'protocoldate' => now()->format('d/m/Y'),
            'etos' => now()->format('Y'),
            'fakelos' => '',
            'thema' => 'Δοκιμαστικό θέμα',
            'in_num' => '', 'in_date' => '', 'in_topos_ekdosis' => '', 'in_arxi_ekdosis' => '',
            'in_paraliptis' => '', 'diekp_eos' => '', 'in_perilipsi' => '',
            'out_date' => '', 'diekp_date' => '', 'sxetiko' => '', 'out_to' => '', 'out_perilipsi' => '',
            'keywords' => '', 'paratiriseis' => '',
            'keep' => '', 'file_inputs_count' => 1,
            'ada1' => '', 'ada2' => '', 'ada3' => '',
            'sendEmailTo' => '', 'reply_to_email' => '',
        ], $overrides);
    }
}
