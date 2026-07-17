<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\PathFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

use ReflectionClass;

use function sprintf;

/**
 * @internal
 *
 * Anti-test for P-043: asserts that PathFinder::lookupTrie uses a
 * by-reference accumulator (return type void, fourth parameter is
 * passed by reference) and that PathFinder exposes a MAX_TRIE_DEPTH
 * constant. Reverting lookupTrie to the spread-merge form (returns
 * array, no by-reference parameter) breaks the reflection assertion.
 */
final class PathFinderAccumulatorTest extends TestCase
{
    #[Test]
    public function lookup_trie_accumulates_by_reference(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users/{id}/posts/{postId}:
    get:
      responses:
        '200':
          description: OK
  /users/{id}/profile:
    get:
      responses:
        '200':
          description: OK
  /users/me:
    get:
      responses:
        '200':
          description: OK
YAML;

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);

        $lookupTrie = new ReflectionMethod($finder, 'lookupTrie');

        self::assertSame(
            'void',
            $lookupTrie->getReturnType()?->getName(),
            'lookupTrie must return void (P-043): no intermediate arrays',
        );

        $parameters = $lookupTrie->getParameters();
        self::assertCount(4, $parameters, 'lookupTrie must take four parameters including the accumulator');

        $accumulator = $parameters[3];
        self::assertTrue(
            $accumulator->isPassedByReference(),
            'Fourth parameter of lookupTrie must be by-reference accumulator (P-043)',
        );

        $type = $accumulator->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame('array', $type->getName());
    }

    #[Test]
    public function max_trie_depth_constant_exists(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /ping:
    get:
      responses:
        '200':
          description: OK
YAML;

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);

        self::assertTrue(
            new ReflectionClass($finder)->hasConstant('MAX_TRIE_DEPTH'),
            'PathFinder must define a MAX_TRIE_DEPTH guard constant (P-043)',
        );
    }

    #[Test]
    public function find_operation_correct_when_multiple_wildcards_match(): void
    {
        $yamlParts = ["openapi: 3.2.0\ninfo:\n  title: Test API\n  version: 1.0.0\npaths:"];

        for ($i = 0; $i < 10; ++$i) {
            $yamlParts[] = sprintf(
                "  /api/v1/resource%d/{id}:\n    get:\n      responses:\n        '200':\n          description: OK",
                $i,
            );
        }

        $yamlParts[] = "  /api/v1/users/{userId}/posts/{postId}:\n    get:\n      responses:\n        '200':\n          description: OK";

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(implode("\n", $yamlParts))
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);

        $operation = $finder->findOperation('/api/v1/users/123/posts/456', 'GET');

        self::assertSame('/api/v1/users/{userId}/posts/{postId}', $operation->path);
        self::assertSame('GET', $operation->method);
    }
}
