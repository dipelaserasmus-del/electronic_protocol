<?php

namespace Tests\Unit;

use App\Support\FolderCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** R1h: folder codes as typed (App\Support\FolderCode); the browser runs the same cases (protocol-form.spec.js). */
class FolderCodeTest extends TestCase
{
    private static function cases(string $group): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../fixtures/folder-code-cases.json'), true)[$group];
    }

    public static function keys(): array { return self::cases('keys'); }
    public static function same(): array { return self::cases('same'); }
    public static function startsWith(): array { return self::cases('startsWith'); }

    #[DataProvider('keys')]
    public function test_key(string $code, string $key): void
    {
        $this->assertSame($key, FolderCode::key($code));
    }

    #[DataProvider('same')]
    public function test_same(string $typed, string $code, bool $expected): void
    {
        $this->assertSame($expected, FolderCode::same($typed, $code));
    }

    #[DataProvider('startsWith')]
    public function test_starts_with(string $typed, string $code, bool $expected): void
    {
        $this->assertSame($expected, FolderCode::startsWith($typed, $code));
    }
}
