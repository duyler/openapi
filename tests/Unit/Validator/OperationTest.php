<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\Operation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operation::class)]
final class OperationTest extends TestCase
{
    #[Test]
    public function __toString_returns_formatted_string(): void
    {
        $operation = new Operation('/users/{id}', 'get');
        $this->assertSame('GET /users/{id}', (string) $operation);
    }

    #[Test]
    public function __toString_lowercases_method(): void
    {
        $operation = new Operation('/users', 'POST');
        $this->assertSame('POST /users', (string) $operation);
    }

    #[Test]
    public function countPlaceholders_returns_correct_count(): void
    {
        $operation = new Operation('/users/{id}', 'GET');
        $this->assertSame(1, $operation->countPlaceholders());
    }

    #[Test]
    public function countPlaceholders_returns_zero_for_static_path(): void
    {
        $operation = new Operation('/users/admin', 'GET');
        $this->assertSame(0, $operation->countPlaceholders());
    }

    #[Test]
    public function countPlaceholders_returns_correct_count_for_multiple_params(): void
    {
        $operation = new Operation('/users/{userId}/posts/{postId}', 'GET');
        $this->assertSame(2, $operation->countPlaceholders());
    }
}
