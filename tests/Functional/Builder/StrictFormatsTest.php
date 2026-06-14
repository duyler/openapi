<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class StrictFormatsTest extends TestCase
{
    private const string SPEC_WITH_UNKNOWN_AND_KNOWN_FORMATS = <<<'YAML'
openapi: 3.1.0
info:
  title: Strict Formats API
  version: 1.0.0
paths:
  /items:
    get:
      operationId: listItems
      parameters:
        - name: code
          in: query
          required: false
          schema:
            type: string
            format: custom-format
        - name: email
          in: query
          required: false
          schema:
            type: string
            format: email
      responses:
        '200':
          description: OK
YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function enable_strict_formats_throws_invalid_format_exception_for_unknown_format(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_UNKNOWN_AND_KNOWN_FORMATS)
            ->enableStrictFormats()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?code=abc-123');

        $this->expectException(InvalidFormatException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function enable_strict_formats_exception_carries_format_and_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_UNKNOWN_AND_KNOWN_FORMATS)
            ->enableStrictFormats()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?code=abc-123');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('custom-format', $exception->format);
            $this->assertStringContainsString('Unknown format "custom-format"', $exception->getMessage());
        }
    }

    #[Test]
    public function without_strict_formats_unknown_format_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_UNKNOWN_AND_KNOWN_FORMATS)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?code=abc-123');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function enable_strict_formats_with_known_format_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_UNKNOWN_AND_KNOWN_FORMATS)
            ->enableStrictFormats()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?email=test@example.com');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function enable_strict_formats_with_known_format_invalid_value_still_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_UNKNOWN_AND_KNOWN_FORMATS)
            ->enableStrictFormats()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?email=not-an-email');

        $this->expectException(InvalidFormatException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function without_strict_formats_unknown_format_in_request_body_passes(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Body Strict Formats API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                identifier:
                  type: string
                  format: custom-format
              required:
                - identifier
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                sprintf('{"identifier": "%s"}', 'abc-123'),
            ));

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function enable_strict_formats_unknown_format_in_request_body_throws(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Body Strict Formats API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                identifier:
                  type: string
                  format: custom-format
              required:
                - identifier
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableStrictFormats()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                sprintf('{"identifier": "%s"}', 'abc-123'),
            ));

        $this->expectException(InvalidFormatException::class);

        $validator->validateRequest($request);
    }
}
