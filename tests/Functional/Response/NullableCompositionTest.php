<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for a JSON:API-shaped OAS 3.0 document where the nullable relationship is
 * expressed as `allOf: [$ref]` plus `nullable: true`, reached through a nested `properties` chain.
 *
 * @see https://github.com/duyler/openapi/issues/50
 *
 * @internal
 */
final class NullableCompositionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
        openapi: 3.0.0
        info:
          title: nullable allOf repro
          version: 1.0.0
        paths:
          '/actions/{actionId}':
            get:
              parameters:
                - name: actionId
                  in: path
                  required: true
                  schema:
                    type: string
              responses:
                '200':
                  description: One action
                  content:
                    application/json:
                      schema:
                        type: object
                        properties:
                          data:
                            $ref: '#/components/schemas/Action'
        components:
          schemas:
            FormIdentifier:
              type: object
              required: [type, id]
              properties:
                type:
                  type: string
                  enum: [forms]
                id:
                  type: string
            Action:
              type: object
              properties:
                type:
                  type: string
                  enum: [actions]
                id:
                  type: string
                relationships:
                  type: object
                  properties:
                    form:
                      type: object
                      properties:
                        data:
                          allOf:
                            - $ref: '#/components/schemas/FormIdentifier'
                          nullable: true
        YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function null_relationship_is_accepted_through_nested_properties(): void
    {
        $this->validateResponseBody(
            '{"data":{"type":"actions","id":"1","relationships":{"form":{"data":null}}}}',
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function populated_relationship_is_accepted_through_nested_properties(): void
    {
        $this->validateResponseBody(
            '{"data":{"type":"actions","id":"1","relationships":{"form":{"data":{"type":"forms","id":"7"}}}}}',
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function invalid_relationship_is_still_rejected_through_nested_properties(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateResponseBody(
            '{"data":{"type":"actions","id":"1","relationships":{"form":{"data":{"type":"widgets"}}}}}',
        );
    }

    private function validateResponseBody(string $body): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/actions/1'),
        );

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);
    }
}
