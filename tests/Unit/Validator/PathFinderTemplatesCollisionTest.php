<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\PathFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathFinder::class)]
final class PathFinderTemplatesCollisionTest extends TestCase
{
    private const string COLLISION_SPEC_YAML = <<<YAML
openapi: 3.2.0
info:
  title: Templates Collision Test API
  version: 1.0.0
paths:
  /api:
    get:
      summary: Root API endpoint
      responses:
        '200':
          description: OK
  /api/__templates__/list:
    get:
      summary: List literal templates segment
      responses:
        '200':
          description: OK
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
YAML;

    #[Test]
    public function literal_templates_segment_does_not_collide_with_ending_path(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/api', 'GET');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('/api', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function literal_templates_segment_matches_correctly(): void
    {
        $finder = $this->createPathFinder();

        $operation = $finder->findOperation('/api/__templates__/list', 'GET');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('/api/__templates__/list', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function existing_path_matching_tests_pass(): void
    {
        $finder = $this->createPathFinder();

        $staticOperation = $finder->findOperation('/users/admin', 'GET');
        $parametrizedOperation = $finder->findOperation('/users/42', 'GET');

        $this->assertSame('/users/admin', $staticOperation->path);
        $this->assertSame('/users/{id}', $parametrizedOperation->path);
    }

    private function createPathFinder(): PathFinder
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COLLISION_SPEC_YAML)
            ->build()
            ->getDocument();

        return new PathFinder($document);
    }
}
