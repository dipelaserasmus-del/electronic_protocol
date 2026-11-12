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
        // the "check for updates" call goes to GitHub on page load; never in tests
        Config::setConfigValueOf('updatesAutoCheck', 0);
        @unlink(storage_path('conf/.denyregister'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('conf/.denyregister'));
        parent::tearDown();
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
