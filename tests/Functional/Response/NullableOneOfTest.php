<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

final class NullableOneOfTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function oneOf_with_nullable_schema_accepts_null_value(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Nullable OneOf Test
  version: 1.0.0
paths:
  /nullable-oneof:
    get:
      summary: Get nullable oneOf
      operationId: getNullableOneOf
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                oneOf:
                  - type: string
                    enum: [value1]
                  - type: string
                    nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nullable-oneof');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('null'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function discriminator_with_nullable_oneof_accepts_null(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Discriminator Nullable OneOf Test
  version: 1.0.0
paths:
  /discriminator-nullable:
    get:
      summary: Get discriminator with nullable oneOf
      operationId: getDiscriminatorNullableOneOf
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                discriminator:
                  propertyName: type
                oneOf:
                  - $ref: '#/components/schemas/TypeA'
                  - type: string
                    nullable: true
components:
  schemas:
    TypeA:
      type: object
      required: [type]
      properties:
        type:
          type: string
          enum: [typeA]
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/discriminator-nullable');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('null'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }
}
