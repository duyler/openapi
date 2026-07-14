<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

final class ParameterStylesE2ETest extends TestCase
{
    private const string MATRIX_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Matrix Style Test API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      summary: Get user by matrix-style path parameter
      parameters:
        - name: id
          in: path
          required: true
          style: matrix
          schema:
            type: string
      responses:
        '200':
          description: User found
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  name:
                    type: string
YAML;

    private const string LABEL_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Label Style Test API
  version: 1.0.0
paths:
  /colors/{color}:
    get:
      summary: Get color by label-style path parameter
      parameters:
        - name: color
          in: path
          required: true
          style: label
          schema:
            type: string
      responses:
        '200':
          description: Color found
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                  hex:
                    type: string
YAML;

    private const string PIPE_DELIMITED_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: PipeDelimited Style Test API
  version: 1.0.0
paths:
  /items:
    get:
      summary: Get items by pipe-delimited query parameter
      parameters:
        - name: ids
          in: query
          required: true
          style: pipeDelimited
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Items found
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  properties:
                    id:
                      type: string
                    name:
                      type: string
YAML;

    private const string SPACE_DELIMITED_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: SpaceDelimited Style Test API
  version: 1.0.0
paths:
  /search:
    get:
      summary: Search with space-delimited query parameter
      parameters:
        - name: tags
          in: query
          style: spaceDelimited
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: Search results
          content:
            application/json:
              schema:
                type: array
                items:
                  type: string
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function matrix_style_path_parameter_validates_successfully(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MATRIX_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/;id=42');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/users/{id}', $operation->path);
    }

    #[Test]
    public function matrix_style_path_parameter_full_request_response_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MATRIX_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/;id=42');

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => '42',
                'name' => 'Alice',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function matrix_style_without_prefix_still_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MATRIX_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users/42');

        $operation = $validator->validateRequest($request);
        $this->assertSame('/users/{id}', $operation->path);
    }

    #[Test]
    public function label_style_path_parameter_validates_successfully(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LABEL_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/colors/.blue');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/colors/{color}', $operation->path);
    }

    #[Test]
    public function label_style_path_parameter_full_request_response_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LABEL_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/colors/.blue');

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'blue',
                'hex' => '#0000FF',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function label_style_without_dot_still_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LABEL_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/colors/blue');

        $operation = $validator->validateRequest($request);
        $this->assertSame('/colors/{color}', $operation->path);
    }

    #[Test]
    public function pipe_delimited_query_parameter_validates_array(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PIPE_DELIMITED_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?ids=a|b|c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/items', $operation->path);
    }

    #[Test]
    public function pipe_delimited_query_parameter_full_request_response_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PIPE_DELIMITED_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?ids=1|2|3');

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                ['id' => '1', 'name' => 'Item 1'],
                ['id' => '2', 'name' => 'Item 2'],
                ['id' => '3', 'name' => 'Item 3'],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pipe_delimited_single_value_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PIPE_DELIMITED_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items?ids=solo');

        $operation = $validator->validateRequest($request);
        $this->assertSame('/items', $operation->path);
    }

    #[Test]
    public function space_delimited_query_parameter_validates_array(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPACE_DELIMITED_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags=red%20green%20blue');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/search', $operation->path);
    }

    #[Test]
    public function space_delimited_query_parameter_full_request_response_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPACE_DELIMITED_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags=php%20api');

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode(['php', 'api'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function matrix_style_with_invalid_path_throws_builder_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MATRIX_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/nonexistent/;id=42');

        $this->expectException(BuilderException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function label_style_with_invalid_path_throws_builder_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LABEL_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/unknown/.blue');

        $this->expectException(BuilderException::class);
        $validator->validateRequest($request);
    }
}
