<?php

namespace App\Services\Logs;

use Illuminate\Support\Facades\Cache;

/**
 * R6e: reads the application's own log (storage/logs) for the log viewer, a day at a time.
 *
 * Only laravel.log and laravel-YYYY-MM-DD.log in storage/logs are read: no name or path ever comes from the browser,
 * only a date. With LOG_CHANNEL=daily each day has its file; an old single laravel.log is split by the date of each
 * entry: its days are found once (a byte range per day, kept while the file only grows), then a day is read by seeking
 * to its range. A day larger than MAX_BYTES (per file) shows its newest part and says so.
 */
final class LogReader
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?\] ([\w.-]+)\.(\w+): ?(.*)$/';

    public bool $truncated = false;

    /** the most read of one day in one file (tests make it small) */
    public int $maxBytes = self::MAX_BYTES;

    public function __construct(private ?string $dir = null)
    {
        $this->dir ??= storage_path('logs');
    }

    /** @return array<string, string> file name => path, only the application's own logs */
    public function files(): array
    {
        $out = [];
        foreach (scandir($this->dir) ?: [] as $name) {
            if (preg_match('/^laravel(-\d{4}-\d{2}-\d{2})?\.log$/', $name) && is_file($this->dir . '/' . $name)) {
                $out[$name] = $this->dir . '/' . $name;
            }
        }
        return $out;
    }

    /** @return list<string> the days with entries (Y-m-d), newest first */
    public function days(): array
    {
        $days = [];
        foreach (array_keys($this->files()) as $name) {
            if (preg_match('/^laravel-(\d{4}-\d{2}-\d{2})\.log$/', $name, $m)) {
                $days[$m[1]] = true;
            }
        }
        foreach (array_keys($this->index()) as $day) {
            $days[$day] = true;
        }
        $days = array_keys($days);
        rsort($days);
        return $days;
    }

    /**
     * The entries of a day, newest first.
     *
     * @return list<array{id: string, time: string, level: string, channel: string, message: string, details: string, file: string}>
     */
    public function day(string $day): array
    {
        $this->truncated = false;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return [];
        }
        $entries = [];
        $files = $this->files();
        if (isset($files["laravel-$day.log"])) {
            $path = $files["laravel-$day.log"];
            $entries = array_merge($entries, $this->parse($path, 0, (int) filesize($path), $day));
        }
        if (isset($files['laravel.log']) && ($range = $this->index()[$day] ?? null)) {
            $entries = array_merge($entries, $this->parse($files['laravel.log'], $range[0], $range[1], $day));
        }
        // newest first; within one second, by place in the file (numbers, not text: review of R6e)
        usort($entries, fn ($a, $b) => [$b['time'], $b['file'], $b['offset']] <=> [$a['time'], $a['file'], $a['offset']]);
        return $entries;
    }

    /** entries between two offsets (at most MAX_BYTES, the newest part), only those of $day */
    private function parse(string $path, int $from, int $to, string $day): array
    {
        if ($to - $from > $this->maxBytes) {
            $this->truncated = true;
            $from = $to - $this->maxBytes;
        }
        if ($to <= $from) {
            return [];   // an empty file (fread of 0 bytes throws: review of R6e)
        }
        $h = fopen($path, 'rb');
        if (! $h) {
            return [];
        }
        fseek($h, $from);
        $data = (string) fread($h, $to - $from);
        fclose($h);
        if ($from > 0 && ($nl = strpos($data, "\n")) !== false && ! preg_match(self::HEADER, strtok($data, "\n"))) {
            $data = substr($data, $nl + 1);   // started inside an entry: from the next line
            $from += $nl + 1;
        }
        if (! mb_check_encoding($data, 'UTF-8')) {
            $data = mb_scrub($data, 'UTF-8');
        }
        $entries = [];
        $current = null;
        $offset = $from;
        foreach (explode("\n", $data) as $line) {
            $length = strlen($line) + 1;
            if (preg_match(self::HEADER, $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = $m[1] === $day ? [
                    'id' => basename($path) . ':' . $offset, 'offset' => $offset, 'time' => $m[2], 'level' => strtolower($m[4]), 'channel' => $m[3],
                    'message' => $m[5], 'details' => '', 'file' => basename($path),
                ] : null;
            } elseif ($current !== null) {
                $current['details'] .= ($current['details'] === '' ? '' : "\n") . rtrim($line, "\r");
            }
            $offset += $length;
        }
        if ($current) {
            $entries[] = $current;
        }
        foreach ($entries as &$e) {
            $e['details'] = rtrim($e['details']);
        }
        return $entries;
    }

    /**
     * laravel.log's days: [day => [first byte, end byte]]. Kept while the file only grows (the part added is read on
     * the next call); a file replaced or shortened is read again.
     */
    public function index(): array
    {
        $path = $this->files()['laravel.log'] ?? null;
        if ($path === null) {
            return [];
        }
        clearstatcache(true, $path);
        $size = (int) filesize($path);
        $key = 'log-index:' . sha1($path);
        $cached = Cache::get($key);
        $index = [];
        $from = 0;
        if (is_array($cached) && ($cached['inode'] ?? null) === fileinode($path) && $cached['size'] <= $size) {
            if ($cached['size'] === $size) {
                return $cached['days'];
            }
            [$index, $from] = [$cached['days'], $cached['size']];
        }
        $h = fopen($path, 'rb');
        if (! $h) {
            return [];
        }
        fseek($h, $from);
        $pos = $from;
        $last = $index ? array_key_last($index) : null;
        while (($line = fgets($h)) !== false) {
            if ($line[0] === '[' && preg_match('/^\[(\d{4}-\d{2}-\d{2})[ T]\d{2}:\d{2}/', $line, $m)) {
                $day = $m[1];
                if (! isset($index[$day])) {
                    $index[$day] = [$pos, $pos];
                }
                $last = $day;
            }
            $pos += strlen($line);
            if ($last !== null) {
                $index[$last][1] = $pos;
            }
        }
        fclose($h);
        Cache::put($key, ['inode' => fileinode($path), 'size' => $size, 'days' => $index], now()->addDays(7));
        return $index;
    }
}
