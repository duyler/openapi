<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\PathFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathFinder::class)]
final class PathFinderTest extends TestCase
{
    private const string TEST_SPEC_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users/admin:
    get:
      summary: Admin panel
      responses:
        '200':
          description: OK
  /users/{id}:
    get:
      summary: Get user by ID
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: OK
    post:
      summary: Update user
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: OK
  /posts:
    get:
      summary: List posts
      responses:
        '200':
          description: OK
  /users/{userId}/posts/{postId}:
    get:
      summary: Get post
      responses:
        '200':
          description: OK
  /products/{category}:
    get:
      summary: Get products by category
      responses:
        '200':
          description: OK
  /products/{category}/{id}:
    get:
      summary: Get product
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function find_operation_static_path_exact_match(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/users/admin', 'GET');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('/users/admin', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function find_operation_parametrized_path_exact_match(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/users/123', 'GET');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function find_operation_with_post_method(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/users/456', 'POST');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function find_operation_with_multiple_path_parameters(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/users/42/posts/99', 'GET');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('/users/{userId}/posts/{postId}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function find_operation_not_found_throws_exception(): void
    {
        $finder = $this->createPathFinder();

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Operation not found: POST /unknown');

        $finder->findOperation('/unknown', 'POST');
    }

    #[Test]
    public function find_operation_method_not_found_throws_exception(): void
    {
        $finder = $this->createPathFinder();

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Operation not found: DELETE /users/123');

        $finder->findOperation('/users/123', 'DELETE');
    }

    #[Test]
    public function find_operation_no_paths_defined_throws_exception(): void
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(<<<'YAML'
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
YAML)
            ->build()
            ->document;

        $finder = new PathFinder($document);

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('No paths defined in OpenAPI specification');

        $finder->findOperation('/users', 'GET');
    }

    #[Test]
    public function prioritize_candidates_static_over_parametrized(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/users/admin', 'GET');

        $this->assertSame('/users/admin', $operation->path);
    }

    #[Test]
    public function prioritize_candidates_multiple_parametrized_paths(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/products/electronics', 'GET');

        $this->assertSame('/products/{category}', $operation->path);
    }

    #[Test]
    public function prioritize_candidates_two_parametrized_paths(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/products/electronics/42', 'GET');

        $this->assertSame('/products/{category}/{id}', $operation->path);
    }

    #[Test]
    public function find_operation_case_insensitive_method(): void
    {
        $finder = $this->createPathFinder();

        $operation1 = $finder->findOperation('/users/123', 'get');
        $operation2 = $finder->findOperation('/users/123', 'GET');
        $operation3 = $finder->findOperation('/users/123', 'Get');

        $this->assertSame('/users/{id}', $operation1->path);
        $this->assertSame('/users/{id}', $operation2->path);
        $this->assertSame('/users/{id}', $operation3->path);
    }

    #[Test]
    public function find_operation_post_method_case_insensitive(): void
    {
        $finder = $this->createPathFinder();

        $operation1 = $finder->findOperation('/users/123', 'post');
        $operation2 = $finder->findOperation('/users/123', 'POST');

        $this->assertSame('/users/{id}', $operation1->path);
        $this->assertSame('/users/{id}', $operation2->path);
    }

    #[Test]
    public function find_operation_with_all_http_methods(): void
    {
        $finder = $this->createPathFinderWithAllMethods();

        $operation1 = $finder->findOperation('/resource', 'GET');
        $this->assertSame('GET', $operation1->method);

        $operation2 = $finder->findOperation('/resource', 'POST');
        $this->assertSame('POST', $operation2->method);

        $operation3 = $finder->findOperation('/resource', 'PUT');
        $this->assertSame('PUT', $operation3->method);

        $operation4 = $finder->findOperation('/resource', 'PATCH');
        $this->assertSame('PATCH', $operation4->method);

        $operation5 = $finder->findOperation('/resource', 'DELETE');
        $this->assertSame('DELETE', $operation5->method);

        $operation6 = $finder->findOperation('/resource', 'HEAD');
        $this->assertSame('HEAD', $operation6->method);

        $operation7 = $finder->findOperation('/resource', 'OPTIONS');
        $this->assertSame('OPTIONS', $operation7->method);

        $operation8 = $finder->findOperation('/resource', 'TRACE');
        $this->assertSame('TRACE', $operation8->method);
    }

    private function createPathFinder(): PathFinder
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TEST_SPEC_YAML)
            ->build()
            ->document;

        return new PathFinder($document);
    }

    private function createPathFinderWithAllMethods(): PathFinder
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /resource:
    get:
      summary: GET
      responses:
        '200':
          description: OK
    post:
      summary: POST
      responses:
        '200':
          description: OK
    put:
      summary: PUT
      responses:
        '200':
          description: OK
    patch:
      summary: PATCH
      responses:
        '200':
          description: OK
    delete:
      summary: DELETE
      responses:
        '200':
          description: OK
    head:
      summary: HEAD
      responses:
        '200':
          description: OK
    options:
      summary: OPTIONS
      responses:
        '200':
          description: OK
    trace:
      summary: TRACE
      responses:
        '200':
          description: OK
YAML;

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->document;

        return new PathFinder($document);
    }
}
