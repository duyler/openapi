<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainsMatchErrorTest extends TestCase
{
    #[Test]
    public function error_message_is_informative(): void
    {
        $error = new ContainsMatchError('/', '/contains');

        self::assertSame('Array does not contain any item matching the schema.', $error->getMessage());
    }

    #[Test]
    public function error_contains_path_information(): void
    {
        $error = new ContainsMatchError('/items/0', '/contains');

        self::assertSame('/items/0', $error->dataPath());
        self::assertSame('/contains', $error->schemaPath());
    }

    #[Test]
    public function error_keyword_is_contains(): void
    {
        $error = new ContainsMatchError('/', '/contains');

        self::assertSame('contains', $error->keyword());
    }

    #[Test]
    public function error_has_suggestion(): void
    {
        $error = new ContainsMatchError('/', '/contains');

        self::assertSame('Ensure at least one item in the array matches the specified schema', $error->suggestion());
    }

    #[Test]
    public function error_params_is_empty(): void
    {
        $error = new ContainsMatchError('/', '/contains');

        self::assertSame([], $error->params());
    }
}
