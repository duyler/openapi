<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Exception;

use Duyler\OpenApi\Schema\Exception\TypeArrayNotSupportedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TypeArrayNotSupportedExceptionTest extends TestCase
{
    #[Test]
    public function message_names_the_dialect_without_spec_content(): void
    {
        $exception = TypeArrayNotSupportedException::forOpenApi30();

        self::assertSame(
            'Type arrays are not allowed in OpenAPI 3.0 documents: the type keyword MUST be a string',
            $exception->getMessage(),
        );
        self::assertSame($exception->getMessage(), (string) $exception);
    }
}
