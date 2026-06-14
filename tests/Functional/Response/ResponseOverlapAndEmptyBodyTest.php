<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\EmptyBodyException;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function sprintf;

final class ResponseOverlapAndEmptyBodyTest extends TestCase
{
    private const string OVERLAP_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Overlap Resolution API
  version: 1.0.0
paths:
  /items:
    get:
      operationId: getItems
      responses:
        '200':
          description: Exact match
          content:
            application/json:
              schema:
                type: object
                required:
                  - source
                properties:
                  source:
                    type: string
                    enum: [exact]
        '2XX':
          description: Range match
          content:
            application/json:
              schema:
                type: object
                required:
                  - source
                properties:
                  source:
                    type: string
                    enum: [range]
        default:
          description: Fallback
          content:
            application/json:
              schema:
                type: object
                required:
                  - source
                properties:
                  source:
                    type: string
                    enum: [default]
YAML;

    private const string REQUIRED_CONTENT_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Required Content API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: getUser
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                required:
                  - name
                properties:
                  name:
                    type: string
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function overlap_resolution_selects_exact_schema_for_status_200(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(200, '{"source":"exact"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function overlap_resolution_rejects_range_value_at_status_200(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(200, '{"source":"range"}');

        $error = $this->catchSchemaError($validator, $response, $operation);

        self::assertInstanceOf(EnumError::class, $error);
        self::assertSame('enum', $error->keyword());
        self::assertStringContainsString('source', $error->dataPath());
    }

    #[Test]
    public function overlap_resolution_selects_range_schema_for_status_201(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(201, '{"source":"range"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function overlap_resolution_rejects_exact_value_at_status_201(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(201, '{"source":"exact"}');

        $error = $this->catchSchemaError($validator, $response, $operation);

        self::assertInstanceOf(EnumError::class, $error);
        self::assertSame('enum', $error->keyword());
    }

    #[Test]
    public function overlap_resolution_selects_default_schema_for_status_500(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(500, '{"source":"default"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function overlap_resolution_rejects_exact_value_at_status_500(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(500, '{"source":"exact"}');

        $error = $this->catchSchemaError($validator, $response, $operation);

        self::assertInstanceOf(EnumError::class, $error);
        self::assertSame('enum', $error->keyword());
    }

    #[Test]
    public function overlap_resolution_selects_range_schema_for_status_without_exact_match(): void
    {
        [$validator, $operation] = $this->buildOverlapValidatorWithOperation();

        $response = $this->createJsonResponse(205, '{"source":"range"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function required_content_accepts_valid_body(): void
    {
        [$validator, $operation] = $this->buildRequiredContentValidatorWithOperation();

        $response = $this->createJsonResponse(200, '{"name":"John"}');

        $this->assertValidationPasses($validator, $response, $operation);
    }

    #[Test]
    public function required_content_rejects_empty_body(): void
    {
        [$validator, $operation] = $this->buildRequiredContentValidatorWithOperation();

        $response = $this->createJsonResponse(200, '');

        $this->expectException(EmptyBodyException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function required_content_rejects_whitespace_only_body(): void
    {
        [$validator, $operation] = $this->buildRequiredContentValidatorWithOperation();

        $response = $this->createJsonResponse(200, "  \n\t ");

        $this->expectException(EmptyBodyException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function required_content_rejects_null_body(): void
    {
        [$validator, $operation] = $this->buildRequiredContentValidatorWithOperation();

        $response = $this->createJsonResponse(200, 'null');

        $error = $this->catchSchemaError($validator, $response, $operation);

        self::assertInstanceOf(TypeMismatchError::class, $error);
        self::assertSame('type', $error->keyword());
        self::assertSame('object', $error->params()['expected']);
    }

    private function buildValidator(string $yaml): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();
    }

    private function createJsonResponse(int $status, string $body): ResponseInterface
    {
        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }

    /**
     * @return array{0: OpenApiValidatorInterface, 1: Operation}
     */
    private function buildOverlapValidatorWithOperation(): array
    {
        $validator = $this->buildValidator(self::OVERLAP_SPEC);
        $operation = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/items'),
        );

        return [$validator, $operation];
    }

    /**
     * @return array{0: OpenApiValidatorInterface, 1: Operation}
     */
    private function buildRequiredContentValidatorWithOperation(): array
    {
        $validator = $this->buildValidator(self::REQUIRED_CONTENT_SPEC);
        $operation = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/users'),
        );

        return [$validator, $operation];
    }

    private function assertValidationPasses(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): void {
        try {
            $validator->validateResponse($response, $operation);
        } catch (AbstractValidationError|ValidationException|UndefinedResponseException $e) {
            self::fail(sprintf(
                'Expected validation to pass, but %s was thrown: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::addToAssertionCount(1);
    }

    private function catchSchemaError(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): AbstractValidationError|ValidationException {
        try {
            $validator->validateResponse($response, $operation);
        } catch (AbstractValidationError|ValidationException $e) {
            return $e;
        }

        self::fail('Expected a schema validation error, but validation passed');
    }
}
