<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * B10: creates `arxeio/backups/<db>_back_<YmdHis>.sql.gz` with mysqldump.
 *
 * The old code ran `mysqldump … | gzip > file` through the shell and checked only gzip's exit code, so a failed
 * dump left an empty .gz and reported success. Here mysqldump runs without a shell (arguments as an array), the
 * password goes in a private temporary options file (not on the command line), the result is checked
 * ("-- Dump completed" at the end) and only then compressed. Any failure throws and leaves no file behind.
 */
class DatabaseBackup
{
    public const DIR = 'arxeio/backups';

    /** Backup file names this app creates or accepts for download/delete (no paths). */
    public const NAME_PATTERN = '/^[A-Za-z0-9_-]+\.sql(\.gz)?$/';

    public function __construct(private ?string $configuredBinary = null) {}

    /** @return string the file name inside arxeio/backups */
    public function create(): string
    {
        $connection = config('database.default');
        $db = config("database.connections.$connection");
        if (($db['driver'] ?? null) !== 'mysql' && ($db['driver'] ?? null) !== 'mariadb') {
            throw new RuntimeException('Το backup υποστηρίζεται μόνο για βάση MySQL/MariaDB.');
        }

        $binary = $this->binary();
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $db['database']).'_back_'.date('YmdHis').'.sql.gz';
        $target = $disk->path(self::DIR.'/'.$name);

        $tmpDir = storage_path('app');
        $options = tempnam($tmpDir, 'dumpcnf');
        $sql = tempnam($tmpDir, 'dumpsql');
        try {
            chmod($options, 0600);
            file_put_contents($options, "[client]\n".
                'user="'.$this->quote($db['username'])."\"\n".
                'password="'.$this->quote((string) $db['password'])."\"\n");

            $args = [
                $binary,
                '--defaults-extra-file='.$options,   // must be the first option
                ...$this->hostArgs($db),
                '--single-transaction',              // consistent copy without locking the app
                '--quick',
                '--no-tablespaces',                  // otherwise MySQL ≥ 8.0.21 needs the PROCESS privilege
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $db['database'],
            ];
            $process = new Process($args, null, null, null, 600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Το mysqldump απέτυχε: '.$this->firstLine($process->getErrorOutput()));
            }
            if (! $this->dumpCompleted($sql)) {
                throw new RuntimeException('Το mysqldump δεν ολοκλήρωσε το αρχείο: '.$this->firstLine($process->getErrorOutput()));
            }

            $this->gzip($sql, $target);
        } catch (\Throwable $e) {
            @unlink($target);
            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            @unlink($options);
            @unlink($sql);
        }

        return $name;
    }

    /** The configured mysqldump if valid, otherwise mysqldump / mariadb-dump found on PATH. */
    public function binary(): string
    {
        $configured = trim((string) $this->configuredBinary);
        if ($configured !== '' && $this->isDumpBinary($configured) && is_file($configured) && is_executable($configured)) {
            return $configured;
        }
        $finder = new ExecutableFinder;
        foreach (['mysqldump', 'mariadb-dump'] as $candidate) {
            if ($found = $finder->find($candidate)) {
                return $found;
            }
        }
        throw new RuntimeException("Δεν βρέθηκε το mysqldump (MYSQLDUMP_PATH: \"$configured\"). Ορίστε τη διαδρομή του στη μεταβλητή MYSQLDUMP_PATH του .env.");
    }

    public static function isValidName(string $name): bool
    {
        return (bool) preg_match(self::NAME_PATTERN, $name);
    }

    /** Only mysqldump / mariadb-dump may be run, whatever MYSQLDUMP_PATH says. */
    private function isDumpBinary(string $path): bool
    {
        return (bool) preg_match('/^(mysqldump|mariadb-dump)(\.exe)?$/i', basename(str_replace('\\', '/', $path)));
    }

    private function hostArgs(array $db): array
    {
        if (! empty($db['unix_socket'])) {
            return ['--socket='.$db['unix_socket']];
        }

        return ['--host='.($db['host'] ?? '127.0.0.1'), '--port='.($db['port'] ?? 3306), '--protocol=TCP'];
    }

    private function quote(string $value): string
    {
        return addcslashes($value, "\\\"\n\r");
    }

    private function dumpCompleted(string $file): bool
    {
        $size = @filesize($file);
        if (! $size) {
            return false;
        }
        $fh = fopen($file, 'rb');
        fseek($fh, max(0, $size - 512));
        $tail = stream_get_contents($fh);
        fclose($fh);

        return str_contains($tail, '-- Dump completed');
    }

    /**
     * Compresses $source into $target and proves the result: every write must be complete, and the finished file
     * must decompress to exactly the source's size (zlib buffers, so a full disk can otherwise go unnoticed).
     */
    public function gzip(string $source, string $target): void
    {
        $failed = new RuntimeException('Δεν ήταν δυνατή η εγγραφή του αρχείου backup (γεμάτος δίσκος ή δικαιώματα;).');
        $in = @fopen($source, 'rb');
        $out = @gzopen($target, 'wb6');
        if (! $in || ! $out) {
            throw $failed;
        }
        try {
            while (! feof($in)) {
                $chunk = fread($in, 1 << 20);
                if ($chunk === false || ($chunk !== '' && @gzwrite($out, $chunk) !== strlen($chunk))) {
                    throw $failed;
                }
            }
        } finally {
            fclose($in);
            $closed = @gzclose($out);
        }
        $expected = filesize($source);
        if (! $closed || $this->gunzippedSize($target, $expected) !== $expected) {
            throw $failed;
        }
    }

    /** Size of the decompressed content (reading stops past $limit), or -1 if it cannot be read. */
    private function gunzippedSize(string $file, int $limit): int
    {
        $fh = @gzopen($file, 'rb');
        if (! $fh) {
            return -1;
        }
        $size = 0;
        while (! gzeof($fh) && $size <= $limit) {
            $chunk = @gzread($fh, 1 << 20);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $size += strlen($chunk);
        }
        gzclose($fh);

        return $size;
    }

    private function firstLine(string $text): string
    {
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_contains($line, 'Using a password')) {
                return mb_substr($line, 0, 300);
            }
        }

        return 'άγνωστο σφάλμα';
    }
}
