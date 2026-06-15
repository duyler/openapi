<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E\OpenApi32;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function sprintf;

/**
 * OA-02: OpenAPI 3.2 additionalOperations — non-standard HTTP methods (COPY, PURGE, etc.)
 * defined inside PathItem via additionalOperations map.
 */
final class AdditionalOperationsTest extends TestCase
{
    private const string ADDITIONAL_OPERATIONS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Additional Operations API
  version: 1.0.0
paths:
  /resource:
    get:
      summary: Standard GET
      responses:
        '200':
          description: OK
    additionalOperations:
      COPY:
        summary: Copy resource
        requestBody:
          required: true
          content:
            application/json:
              schema:
                type: object
                required:
                  - destination
                properties:
                  destination:
                    type: string
        responses:
          '201':
            description: Copied
      PURGE:
        summary: Purge cache
        responses:
          '204':
            description: Purged
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function additional_operation_copy_matches_request_and_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory
            ->createServerRequest('COPY', '/resource')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream('{"destination":"/archive"}'),
            );

        $operation = $validator->validateRequest($request);

        self::assertSame('/resource', $operation->path);
        self::assertSame('COPY', $operation->method);
    }

    #[Test]
    public function additional_operation_purge_matches_request_and_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('PURGE', '/resource');

        $operation = $validator->validateRequest($request);

        self::assertSame('/resource', $operation->path);
        self::assertSame('PURGE', $operation->method);
    }

    #[Test]
    public function additional_operation_method_is_case_insensitive(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory
            ->createServerRequest('copy', '/resource')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"destination":"/archive"}'));

        $operation = $validator->validateRequest($request);

        self::assertSame('/resource', $operation->path);
        self::assertSame('copy', $operation->method);
    }

    #[Test]
    public function additional_operation_with_invalid_body_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory
            ->createServerRequest('COPY', '/resource')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{}'));

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'COPY with missing required destination must fail validation');
        self::assertSame('required', $caught->getErrors()[0]->keyword());
    }

    #[Test]
    public function unknown_additional_method_throws_builder_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('LINK', '/resource');

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (BuilderException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'LINK not declared must throw BuilderException');
        self::assertStringContainsString('LINK', $caught->getMessage());
    }

    #[Test]
    public function standard_get_still_matches_alongside_additional_operations(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('GET', '/resource');

        $operation = $validator->validateRequest($request);

        self::assertSame('/resource', $operation->path);
        self::assertSame('GET', $operation->method);
    }

    #[Test]
    public function additional_operation_path_template_matches_dynamic_segment(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Templated Additional Operations API
  version: 1.0.0
paths:
  /resource/{id}:
    additionalOperations:
      LOCK:
        summary: Lock resource
        parameters:
          - name: id
            in: path
            required: true
            schema:
              type: string
        responses:
          '200':
            description: Locked
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('LOCK', '/resource/42');

        $operation = $validator->validateRequest($request);

        self::assertSame('/resource/{id}', $operation->path);
        self::assertSame('LOCK', $operation->method);
    }

    #[Test]
    public function string_representation_of_additional_operation_includes_method_and_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory
            ->createServerRequest('PURGE', '/resource');

        $operation = $validator->validateRequest($request);

        self::assertSame('PURGE /resource', (string) $operation);
    }

    #[Test]
    public function additional_operations_request_body_validates_against_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ADDITIONAL_OPERATIONS_SPEC)
            ->build();

        $request = $this->factory
            ->createServerRequest('COPY', '/resource')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream('{"destination":"new-location"}'),
            );

        $succeeded = false;

        try {
            $validator->validateRequest($request);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected COPY request to pass validation, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
