<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Exception;

use Duyler\OpenApi\Schema\Exception\VocabularyNotSupportedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_contains;
use function strlen;
use function str_repeat;
use function str_starts_with;

final class VocabularyNotSupportedExceptionTest extends TestCase
{
    #[Test]
    public function message_contains_vocabulary_uri(): void
    {
        $exception = new VocabularyNotSupportedException(
            vocabularyUri: 'https://example.com/vocab/custom',
        );

        self::assertSame(
            'Document requires unsupported vocabulary: https://example.com/vocab/custom',
            $exception->getMessage(),
        );
        self::assertSame('https://example.com/vocab/custom', $exception->vocabularyUri);
        self::assertSame($exception->getMessage(), (string) $exception);
    }

    #[Test]
    public function oversized_uri_is_truncated_in_message_and_property(): void
    {
        $oversizedUri = sprintf('https://example.com/vocab/%s', str_repeat('a', 512));

        $exception = new VocabularyNotSupportedException(vocabularyUri: $oversizedUri);

        self::assertTrue(strlen($exception->vocabularyUri) < strlen($oversizedUri));
        self::assertTrue(str_starts_with($exception->getMessage(), 'Document requires unsupported vocabulary: https://example.com/vocab/aaa'));
        self::assertTrue(str_contains($exception->getMessage(), 'truncated'));
    }
}
