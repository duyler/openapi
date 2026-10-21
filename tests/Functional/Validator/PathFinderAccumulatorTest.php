<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\PathFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

use ReflectionClass;
use Duyler\OpenApi\Validator\Internal\TrieLookup;
use Duyler\OpenApi\Validator\Internal\TrieMatches;

use function sprintf;

/**
 * @internal
 */
final class PathFinderAccumulatorTest extends TestCase
{
    #[Test]
    public function lookup_trie_accumulates_into_by_object_state(): void
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

        $trieLookup = new TrieLookup();
        $lookupTrie = new ReflectionMethod($trieLookup, 'lookupTrie');

        self::assertSame(
            'void',
            $lookupTrie->getReturnType()?->getName(),
            'lookupTrie must return void: no intermediate arrays',
        );

        $parameters = $lookupTrie->getParameters();
        self::assertCount(4, $parameters, 'lookupTrie must take four parameters including the accumulator');

        $accumulator = $parameters[3];
        self::assertFalse(
            $accumulator->isPassedByReference(),
            'Fourth parameter of lookupTrie must be a by-object accumulator, not by-reference',
        );

        $type = $accumulator->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame(TrieMatches::class, $type->getName());
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
            new ReflectionClass(TrieLookup::class)->hasConstant('MAX_TRIE_DEPTH'),
            'TrieLookup must define a MAX_TRIE_DEPTH guard constant',
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
