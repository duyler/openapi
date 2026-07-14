<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ParameterValidatorContextTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function nullable_as_type_disabled_rejects_null_query_param(): void
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
      parameters:
        - name: filter
          in: querystring
          content:
            application/json:
              schema:
                type: string
                nullable: true
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->disableNullableAsType()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test?null');

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function nullable_as_type_enabled_allows_null_query_param(): void
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
      parameters:
        - name: filter
          in: querystring
          content:
            application/json:
              schema:
                type: string
                nullable: true
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test?null');

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function prefer_object_strategy_rejects_empty_cookie_for_array_schema(): void
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
      parameters:
        - name: tags
          in: cookie
          style: cookie
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/test')
            ->withHeader('Cookie', 'tags=');

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function allow_both_strategy_accepts_empty_cookie_for_array_schema(): void
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
      parameters:
        - name: tags
          in: cookie
          style: cookie
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/test')
            ->withHeader('Cookie', 'tags=');

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function nullable_as_type_disabled_rejects_null_required_query_param(): void
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
      parameters:
        - name: filter
          in: querystring
          required: true
          content:
            application/json:
              schema:
                type: string
                nullable: true
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->disableNullableAsType()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test?null');

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function nullable_as_type_enabled_allows_null_required_query_param(): void
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
      parameters:
        - name: filter
          in: querystring
          required: true
          content:
            application/json:
              schema:
                type: string
                nullable: true
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test?null');

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function prefer_object_strategy_rejects_empty_path_array_param(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test/{tags}:
    get:
      operationId: test
      parameters:
        - name: tags
          in: path
          required: true
          style: label
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test/.');

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function allow_both_strategy_accepts_empty_path_array_param(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test/{tags}:
    get:
      operationId: test
      parameters:
        - name: tags
          in: path
          required: true
          style: label
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test/.');

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function prefer_object_strategy_rejects_empty_request_header_for_array_schema(): void
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
      parameters:
        - name: X-Tags
          in: header
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/test')
            ->withHeader('X-Tags', '');

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function allow_both_strategy_accepts_empty_request_header_for_array_schema(): void
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
      parameters:
        - name: X-Tags
          in: header
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/test')
            ->withHeader('X-Tags', '');

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }
}
