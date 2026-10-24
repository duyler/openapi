<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Exception;

use Duyler\OpenApi\Schema\Exception\InvalidContentTypeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InvalidContentTypeExceptionTest extends TestCase
{
    #[Test]
    public function message_names_the_restriction_without_spec_content(): void
    {
        $exception = InvalidContentTypeException::forEncoding();

        self::assertSame(
            'Encoding contentType must be a string or a non-empty list of strings',
            $exception->getMessage(),
        );
        self::assertSame($exception->getMessage(), (string) $exception);
    }
}
