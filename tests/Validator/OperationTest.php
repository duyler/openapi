<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

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
    public function hasPlaceholders_returns_true_for_parametrized_path(): void
    {
        $operation = new Operation('/users/{id}', 'GET');
        $this->assertTrue($operation->hasPlaceholders());
    }

    #[Test]
    public function hasPlaceholders_returns_false_for_static_path(): void
    {
        $operation = new Operation('/users/admin', 'GET');
        $this->assertFalse($operation->hasPlaceholders());
    }

    #[Test]
    public function hasPlaceholders_returns_true_for_multiple_params(): void
    {
        $operation = new Operation('/users/{userId}/posts/{postId}', 'GET');
        $this->assertTrue($operation->hasPlaceholders());
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

    #[Test]
    public function parseParameters_extracts_single_parameter(): void
    {
        $operation = new Operation('/users/{id}', 'GET');
        $params = $operation->parseParameters('/users/123');

        $this->assertSame(['id' => '123'], $params);
    }

    #[Test]
    public function parseParameters_extracts_multiple_parameters(): void
    {
        $operation = new Operation('/users/{userId}/posts/{postId}', 'GET');
        $params = $operation->parseParameters('/users/42/posts/99');

        $this->assertSame(['userId' => '42', 'postId' => '99'], $params);
    }

    #[Test]
    public function parseParameters_returns_empty_for_static_path(): void
    {
        $operation = new Operation('/users/admin', 'GET');
        $params = $operation->parseParameters('/users/admin');

        $this->assertSame([], $params);
    }

    #[Test]
    public function parseParameters_handles_special_characters(): void
    {
        $operation = new Operation('/users/{id}/posts/{slug}', 'GET');
        $params = $operation->parseParameters('/users/123/posts/my-post-slug');

        $this->assertSame(['id' => '123', 'slug' => 'my-post-slug'], $params);
    }
}
