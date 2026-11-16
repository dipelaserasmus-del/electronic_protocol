<?php

namespace Tests\Unit;

use App\Support\FileNames;
use PHPUnit\Framework\TestCase;

/** F08: stored attachment names fit Linux's 255 bytes and the 255-character savedPath column. */
class FileNamesTest extends TestCase
{
    private const PREFIX = '1234-20261116_12_';

    public function test_short_names_are_unchanged(): void
    {
        $this->assertSame(self::PREFIX.'Πρόσκληση.pdf', FileNames::fit(self::PREFIX, 'Πρόσκληση.pdf'));
    }

    /** @return iterable<string, array{string}> */
    public static function longNames(): iterable
    {
        yield 'Greek, no spaces' => [str_repeat('Αίτηση', 40).'.pdf'];
        yield 'Greek with spaces' => [str_repeat('Αίτηση άδειας ', 30).'.docx'];
        yield 'emoji (4 bytes)' => [str_repeat('📎🎉', 80).'.pdf'];
        yield 'mixed, odd lengths' => ['α'.str_repeat('β😀γ ', 50).'.xlsx'];
        yield 'ASCII' => [str_repeat('a', 300).'.pdf'];
        yield 'no extension' => [str_repeat('Αίτηση', 40)];
        yield 'long "extension"' => ['Αίτηση.'.str_repeat('α', 200)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('longNames')]
    public function test_long_names_fit_in_bytes_and_characters_and_keep_the_extension(string $name): void
    {
        $fitted = FileNames::fit(self::PREFIX, $name);

        $this->assertLessThanOrEqual(FileNames::MAX_BYTES, strlen($fitted));
        $this->assertLessThanOrEqual(FileNames::MAX_PATH_CHARS, mb_strlen($fitted));
        $this->assertTrue(mb_check_encoding($fitted, 'UTF-8'), 'no character cut in half');
        $this->assertStringStartsWith(self::PREFIX, $fitted);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if ($extension !== '' && strlen($extension) <= 10) {
            $this->assertStringEndsWith('.'.$extension, $fitted);
        }
        $this->assertGreaterThan(strlen(self::PREFIX) + 20, strlen($fitted), 'as much of the name as fits is kept');
    }

    /** Review finding: shortening the shown name must keep its extension. */
    public function test_clean_keeps_the_extension_of_a_long_name(): void
    {
        $clean = FileNames::clean(str_repeat('a', 201).'.pdf');
        $this->assertSame(FileNames::MAX_CHARS, mb_strlen($clean));
        $this->assertStringEndsWith('.pdf', $clean);
        $this->assertStringEndsWith('.pdf', FileNames::clean(str_repeat('Αίτηση ', 40).'.pdf'));
    }

    /** Review finding: folder + "/" + stored name must fit the 255-character savedPath column, also for long folders. */
    public function test_stored_path_fits_the_column_with_a_long_folder(): void
    {
        foreach ([1, 47, 80, 150] as $folderLength) {
            $dir = 'arxeio/'.str_repeat('Φ', $folderLength);
            $fitted = FileNames::fit(self::PREFIX, str_repeat('a', 190).'.pdf', $dir);
            $this->assertLessThanOrEqual(FileNames::MAX_PATH_CHARS, mb_strlen("$dir/$fitted"), "folder of $folderLength");
            $this->assertLessThanOrEqual(FileNames::MAX_BYTES, strlen($fitted));
            $this->assertStringEndsWith('.pdf', $fitted);
        }
    }

    public function test_clean_replaces_unsafe_characters_as_before(): void
    {
        $this->assertSame('a-b-c-d.pdf', FileNames::clean('a/b\\c:d.pdf'));
        $traversal = FileNames::clean('../../etc/passwd');
        $this->assertStringNotContainsString('/', $traversal);
        $this->assertDoesNotMatchRegularExpression('/^[.-]/', $traversal, 'no leading dots');
        $this->assertSame('Αίτηση 📎 άδειας.pdf', FileNames::clean('Αίτηση 📎 άδειας.pdf'));
    }

    public function test_names_without_usable_characters_get_a_name_and_keep_or_get_an_extension(): void
    {
        $this->assertSame('συνημμένο.pdf', FileNames::clean('???.pdf'));
        $this->assertSame('συνημμένο.pdf', FileNames::clean(null, 'application/pdf'));
        $this->assertSame('συνημμένο.docx', FileNames::clean('', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame('συνημμένο', FileNames::clean(null));
        $this->assertSame('συνημμένο', FileNames::clean('...'));
    }
}
