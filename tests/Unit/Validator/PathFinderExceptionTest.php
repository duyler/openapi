<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use Duyler\OpenApi\Validator\PathFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PathFinder::class)]
#[CoversClass(OperationNotFoundException::class)]
final class PathFinderExceptionTest extends TestCase
{
    private const string TEST_SPEC_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      summary: Get user by ID
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function find_operation_throws_operation_not_found_for_unknown_path(): void
    {
        $finder = $this->createPathFinder();

        $this->expectException(OperationNotFoundException::class);

        $finder->findOperation('/unknown', 'GET');
    }

    #[Test]
    public function find_operation_throws_operation_not_found_for_unknown_method(): void
    {
        $finder = $this->createPathFinder();

        $this->expectException(OperationNotFoundException::class);

        $finder->findOperation('/users/123', 'DELETE');
    }

    #[Test]
    public function operation_not_found_exception_contains_path_method_and_sanitised_message(): void
    {
        $finder = $this->createPathFinder();

        try {
            $finder->findOperation('/users/42', 'PUT');
            self::fail('Expected OperationNotFoundException to be thrown');
        } catch (OperationNotFoundException $exception) {
            self::assertSame('/users/42', $exception->requestPath);
            self::assertSame('PUT', $exception->method);
            self::assertSame('No operation matches the request', $exception->getMessage());
            self::assertInstanceOf(RuntimeException::class, $exception);
        }
    }

    #[Test]
    public function find_operation_throws_builder_exception_for_empty_spec(): void
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(<<<'YAML'
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
YAML)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('No paths defined in OpenAPI specification');

        $finder->findOperation('/users', 'GET');
    }

    private function createPathFinder(): PathFinder
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TEST_SPEC_YAML)
            ->build()
            ->getDocument();

        return new PathFinder($document);
    }
}
