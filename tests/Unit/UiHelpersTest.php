<?php

namespace Tests\Unit;

use App\Support\DateInput;
use App\Support\FileBadge;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** N04: rules behind the shared components. The date cases are shared with the browser test of the JS twin. */
class UiHelpersTest extends TestCase
{
    private static function cases(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../fixtures/date-input-cases.json'), true);
    }

    public static function expandCases(): iterable
    {
        foreach (self::cases()['cases'] as [$typed, $expected]) {
            yield "'$typed'" => [$typed, $expected];
        }
    }

    #[DataProvider('expandCases')]
    public function test_date_input_expands_what_the_user_types(string $typed, ?string $expected): void
    {
        $this->assertSame($expected, DateInput::expand($typed, CarbonImmutable::parse(self::cases()['today'])));
    }

    public static function shiftCases(): iterable
    {
        foreach (self::cases()['shift'] as [$from, $days, $expected]) {
            yield "'$from' $days" => [$from, $days, $expected];
        }
    }

    #[DataProvider('shiftCases')]
    public function test_date_input_previous_and_next_day(string $from, int $days, string $expected): void
    {
        $this->assertSame($expected, DateInput::shift($from, $days, CarbonImmutable::parse(self::cases()['today'])));
    }

    public function test_file_badge_kind_by_extension_or_mime_type(): void
    {
        $this->assertSame(['PDF', 'danger'], FileBadge::kind('Απόφαση.PDF'));
        $this->assertSame(['DOC', 'primary'], FileBadge::kind('αίτηση.docx'));
        $this->assertSame(['XLS', 'success'], FileBadge::kind('πίνακας.xlsx'));
        $this->assertSame(['EML', 'dark'], FileBadge::kind('email_20260305_101500.eml'));
        $this->assertSame(['PDF', 'danger'], FileBadge::kind('', 'application/pdf'), 'nameless attachment');
        $this->assertSame(['DAT', 'secondary'], FileBadge::kind('winmail.dat'));
        $this->assertSame(['ΑΡΧΕΙΟ', 'secondary'], FileBadge::kind('noextension'));
    }

    public function test_file_badge_size_is_readable_with_greek_decimal_comma(): void
    {
        $this->assertSame('0 B', FileBadge::size(0));
        $this->assertSame('950 B', FileBadge::size(950));
        $this->assertSame('12 KB', FileBadge::size(12 * 1024 + 100));
        $this->assertSame('1,2 MB', FileBadge::size((int) (1.2 * 1024 * 1024)));
        $this->assertSame('20 MB', FileBadge::size(21_099_835));   // 20.1 MiB, as Windows Explorer shows it
        $this->assertSame('', FileBadge::size(null));
    }
}
