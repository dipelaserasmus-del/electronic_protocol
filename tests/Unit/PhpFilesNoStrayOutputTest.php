<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * B10 (found by the browser test): config/imapFolders.php began with 6 spaces/newlines before `<?php`.
 * Laravel loads every config file on every request, so those bytes were sent before every page and
 * every download: backup .gz files did not open, and the last 6 bytes were cut off (Content-Length).
 */
class PhpFilesNoStrayOutputTest extends TestCase
{
    public function test_php_files_loaded_by_the_app_start_with_the_open_tag_and_have_no_closing_tag(): void
    {
        $root = dirname(__DIR__, 2);
        $bad = [];
        foreach (['app', 'config', 'routes', 'bootstrap', 'database'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/$dir", \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $path = $file->getPathname();
                if ($file->getExtension() !== 'php' || str_contains($path, '/bootstrap/cache/')) {
                    continue;
                }
                $code = file_get_contents($path);
                if (!str_starts_with($code, '<?php')) {
                    $bad[] = substr($path, strlen($root) + 1) . ': text before <?php';
                } elseif (preg_match('/\?>\s+$/', $code) === 1) {
                    $bad[] = substr($path, strlen($root) + 1) . ': whitespace after the closing ?>';
                }
            }
        }

        $this->assertSame([], $bad, 'These files send stray bytes before every response');
    }
}
