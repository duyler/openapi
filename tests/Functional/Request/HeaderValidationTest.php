<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HeaderValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function header_multiple_values_via_with_added_header_passes_for_string_type(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Custom
          in: header
          required: true
          schema:
            type: string
            pattern: '/^a, b$/'
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Custom', 'a')
            ->withAddedHeader('X-Custom', 'b');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/test', $operation->path);
    }

    #[Test]
    public function header_multiple_values_exceeding_max_length_throws_error(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Custom
          in: header
          required: true
          schema:
            type: string
            maxLength: 3
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Custom', 'a')
            ->withAddedHeader('X-Custom', 'b');

        $this->expectException(MaxLengthError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function header_unicode_utf8_value_passes_for_string_type(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Name
          in: header
          required: true
          schema:
            type: string
            maxLength: 11
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Name', 'José García');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/test', $operation->path);
    }

    #[Test]
    public function header_unicode_utf8_value_exceeding_max_length_by_codepoints_throws_error(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Name
          in: header
          required: true
          schema:
            type: string
            maxLength: 10
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Name', 'José García');

        $this->expectException(MaxLengthError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function header_empty_value_passes_without_min_length(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Empty
          in: header
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Empty', '');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/test', $operation->path);
    }

    #[Test]
    public function header_empty_value_with_min_length_throws_error(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Empty
          in: header
          required: true
          schema:
            type: string
            minLength: 1
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Empty', '');

        $this->expectException(MinLengthError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function header_numeric_value_coerced_to_integer_passes(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Id
          in: header
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Id', '42');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/test', $operation->path);
    }

    #[Test]
    public function header_numeric_overflow_on_coercion_throws_type_mismatch(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Id
          in: header
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Id', '99999999999999999999');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function header_array_type_with_comma_separated_string_passes(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Tags
          in: header
          required: true
          style: form
          explode: false
          schema:
            type: array
            items:
              type: string
            minItems: 1
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Tags', 'a,b,c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/test', $operation->path);
    }

    #[Test]
    public function header_array_type_with_single_value_throws_type_mismatch(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: Headers API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: X-Tags
          in: header
          required: true
          style: form
          explode: false
          schema:
            type: array
            items:
              type: string
            minItems: 2
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test')
            ->withHeader('X-Tags', 'solo');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }
}
