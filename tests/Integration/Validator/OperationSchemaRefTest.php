<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OperationSchemaRefTest extends TestCase
{
    private const string SPEC_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Operation Schema Reference API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      operationId: getUserById
      summary: Get user by ID
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: User found
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/User'
      security:
        - bearerAuth: []
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
YAML;

    private OpenApiValidator $validator;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_YAML)
            ->build();
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function exposes_operation_id_from_schema(): void
    {
        $request = $this->psrFactory->createServerRequest('GET', '/users/42');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame('getUserById', $operation->operationId);
    }

    #[Test]
    public function exposes_schema_operation_reference(): void
    {
        $request = $this->psrFactory->createServerRequest('GET', '/users/42');

        $operation = $this->validator->validateRequest($request);

        $schemaOperation = $operation->schemaOperation;
        $this->assertInstanceOf(SchemaOperation::class, $schemaOperation);
        $this->assertSame('getUserById', $schemaOperation?->operationId);
        $this->assertNotNull($schemaOperation?->responses);
        $this->assertNotNull($schemaOperation?->security);
    }
}
