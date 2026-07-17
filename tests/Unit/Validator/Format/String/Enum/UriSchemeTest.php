<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String\Enum;

use Duyler\OpenApi\Validator\Format\String\Enum\UriScheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriScheme::class)]
final class UriSchemeTest extends TestCase
{
    #[Test]
    public function cases_contain_all_supported_schemes(): void
    {
        $values = array_map(static fn(UriScheme $scheme): string => $scheme->value, UriScheme::cases());

        self::assertSame(
            ['http', 'https', 'ftp', 'ftps', 'file', 'mailto', 'tel', 'data'],
            $values,
        );
    }

    #[Test]
    public function try_from_returns_case_for_valid_lowercase_value(): void
    {
        self::assertSame(UriScheme::Http, UriScheme::tryFrom('http'));
        self::assertSame(UriScheme::Https, UriScheme::tryFrom('https'));
        self::assertSame(UriScheme::File, UriScheme::tryFrom('file'));
        self::assertSame(UriScheme::Data, UriScheme::tryFrom('data'));
    }

    #[Test]
    public function try_from_returns_null_for_unsupported_scheme(): void
    {
        self::assertNull(UriScheme::tryFrom('javascript'));
        self::assertNull(UriScheme::tryFrom(''));
        self::assertNull(UriScheme::tryFrom('HTTP'));
    }
}
