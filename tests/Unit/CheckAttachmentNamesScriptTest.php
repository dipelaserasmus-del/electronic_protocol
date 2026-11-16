<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** F08: tools/check-attachment-names.php (run by the owner on the old server's files before moving them). */
class CheckAttachmentNamesScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/f08-names-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/arxeio/Φ.1', 0777, true);
        file_put_contents($this->dir.'/arxeio/Φ.1/12-20261116_Πρόσκληση.pdf', 'x');   // 25 characters, 34 bytes
        file_put_contents($this->dir.'/arxeio/Φ.1/13-20261116_a.pdf', 'x');            // 21 bytes
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/arxeio/Φ.1/*') as $f) {
            unlink($f);
        }
        rmdir($this->dir.'/arxeio/Φ.1');
        rmdir($this->dir.'/arxeio');
        rmdir($this->dir);
    }

    /** @return array{0: int, 1: string} exit code, output */
    private function check(string ...$args): array
    {
        exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, __DIR__.'/../../tools/check-attachment-names.php', ...$args])).' 2>&1', $out, $code);

        return [$code, implode("\n", $out)];
    }

    public function test_all_names_fit(): void
    {
        [$code, $out] = $this->check($this->dir);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Files checked:            2', $out);
        $this->assertStringContainsString('Names over 255 bytes:     0', $out);
        $this->assertStringContainsString('Longest name:             34 bytes', $out, 'bytes, not characters');
    }

    public function test_names_over_the_limit_are_counted_and_listed_only_on_request(): void
    {
        [$code, $out] = $this->check($this->dir, '--max-bytes=30');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Names over 30 bytes:     1', $out);
        $this->assertStringNotContainsString('Πρόσκληση', $out, 'no file names without --list');

        [, $listed] = $this->check($this->dir, '--max-bytes=30', '--list');
        $this->assertStringContainsString('arxeio/Φ.1/12-20261116_Πρόσκληση.pdf', $listed);
        $this->assertStringNotContainsString('13-20261116_a.pdf', $listed);
    }

    public function test_wrong_usage(): void
    {
        $this->assertSame(2, $this->check($this->dir.'/missing')[0]);
    }
}
