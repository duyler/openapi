<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
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

    #[Test]
    public function constructs_with_defaults_when_optional_fields_omitted(): void
    {
        $operation = new Operation('/users', 'GET');

        $this->assertNull($operation->operationId);
        $this->assertSame([], $operation->pathParameters);
        $this->assertNull($operation->schemaOperation);
    }

    #[Test]
    public function exposes_path_parameters_typed_as_string_map(): void
    {
        $operation = new Operation(
            path: '/users/{id}',
            method: 'GET',
            pathParameters: ['id' => '42'],
        );

        $this->assertSame(['id' => '42'], $operation->pathParameters);
    }

    #[Test]
    public function exposes_operation_id_from_schema(): void
    {
        $operation = new Operation(
            path: '/users/{id}',
            method: 'GET',
            operationId: 'getUserById',
        );

        $this->assertSame('getUserById', $operation->operationId);
    }

    #[Test]
    public function exposes_schema_operation_reference(): void
    {
        $schemaOperation = new SchemaOperation(operationId: 'getUserById');

        $operation = new Operation(
            path: '/users/{id}',
            method: 'GET',
            operationId: $schemaOperation->operationId,
            schemaOperation: $schemaOperation,
        );

        $this->assertSame($schemaOperation, $operation->schemaOperation);
        $this->assertSame('getUserById', $operation->schemaOperation->operationId);
    }

    #[Test]
    public function to_string_uses_method_and_template_path(): void
    {
        $operation = new Operation(
            path: '/users/{id}/posts/{postId}',
            method: 'get',
            pathParameters: ['id' => '42', 'postId' => '7'],
        );

        $this->assertSame('GET /users/{id}/posts/{postId}', (string) $operation);
    }
}
