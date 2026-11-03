<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Exception;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(InvalidSchemaException::class)]
final class InvalidSchemaExceptionTest extends TestCase
{
    #[Test]
    public function for_unknown_keyword_names_the_keyword_and_the_schema_pointer(): void
    {
        $exception = InvalidSchemaException::forUnknownKeyword('requird', '#/components/schemas/Name');

        self::assertSame(
            'Unknown schema keyword \'requird\' at #/components/schemas/Name'
            . ' (register a keyword validator for it, allow-list it, or disable strict keywords)',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function long_keyword_is_truncated_without_spec_content_disclosure(): void
    {
        $keyword = str_repeat('a', 512);

        $exception = InvalidSchemaException::forUnknownKeyword($keyword, '#/components/schemas/Name');

        self::assertStringContainsString('(truncated, 512 bytes total)', $exception->getMessage());
    }

    #[Test]
    public function long_pointer_is_truncated(): void
    {
        $pointer = '#/components/schemas/' . str_repeat('a', 512);

        $exception = InvalidSchemaException::forUnknownKeyword('requird', $pointer);

        self::assertStringContainsString('(truncated, 533 bytes total)', $exception->getMessage());
    }

    #[Test]
    public function message_is_the_string_representation(): void
    {
        $exception = InvalidSchemaException::forUnknownKeyword('requird', '#/components/schemas/Name');

        self::assertSame($exception->getMessage(), (string) $exception);
    }
}
