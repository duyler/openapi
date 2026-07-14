<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ResponseHeadersValidatorContextTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function prefer_object_strategy_rejects_empty_header_for_array_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      operationId: test
      responses:
        '200':
          description: Success
          headers:
            X-Tags:
              schema:
                type: array
                items:
                  type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Tags', '')
            ->withBody($this->psrFactory->createStream('{}'));

        $this->expectException(TypeMismatchError::class);

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function allow_both_strategy_accepts_empty_header_for_array_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      operationId: test
      responses:
        '200':
          description: Success
          headers:
            X-Tags:
              schema:
                type: array
                items:
                  type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Tags', '')
            ->withBody($this->psrFactory->createStream('{}'));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Known limitation: ResponseHeadersValidator::coerceValue has no branch for
     * type "object", so an empty header value stays a string '' instead of being
     * coerced into an empty object. EmptyArrayStrategy only applies to empty
     * arrays, not strings. Therefore the acceptance criterion "PreferObject +
     * empty header value + {type: object} => accept" cannot be fulfilled without
     * a separate coercion change (out of scope for FU-004, which covers only
     * context propagation). This test locks the current behavior and documents
     * the limitation.
     */
    #[Test]
    public function prefer_object_strategy_empty_header_object_is_not_supported_by_current_coerce_value(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      operationId: test
      responses:
        '200':
          description: Success
          headers:
            X-Meta:
              schema:
                type: object
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Meta', '')
            ->withBody($this->psrFactory->createStream('{}'));

        $this->expectException(TypeMismatchError::class);

        $validator->validateResponse($response, $operation);
    }
}
