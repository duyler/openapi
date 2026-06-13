<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use Duyler\OpenApi\Validator\PathFinder;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Server\ServerPathMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(PathFinder::class)]
#[CoversClass(PathParser::class)]
final class PathFinderPerformanceTest extends TestCase
{
    #[Test]
    public function find_last_route_among_100_generates_no_exceptions(): void
    {
        $yamlParts = ["openapi: 3.1.0\ninfo:\n  title: Test API\n  version: 1.0.0\npaths:"];

        for ($i = 0; $i < 100; $i++) {
            $yamlParts[] = sprintf(
                "  /resource%d/{id}:\n    get:\n      responses:\n        '200':\n          description: OK",
                $i,
            );
        }

        $yaml = implode("\n", $yamlParts);

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);

        set_error_handler(function (int $errno, string $errstr): bool {
            if (str_contains($errstr, 'PathMismatchException')) {
                restore_error_handler();
                $this->fail('PathMismatchException should not be generated during path search');
            }

            return false;
        });

        $operation = $finder->findOperation('/resource99/123', 'GET');

        restore_error_handler();

        $this->assertSame('/resource99/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function try_match_path_returns_null_on_mismatch(): void
    {
        $parser = new PathParser();

        $result = $parser->tryMatchPath('/users/123/posts', '/users/{id}/posts/{postId}');

        $this->assertNull($result);
    }

    #[Test]
    public function try_match_path_returns_params_on_match(): void
    {
        $parser = new PathParser();

        $result = $parser->tryMatchPath('/users/123/posts/456', '/users/{userId}/posts/{postId}');

        $this->assertSame(['userId' => '123', 'postId' => '456'], $result);
    }

    #[Test]
    public function try_match_path_consistent_with_match_path(): void
    {
        $parser = new PathParser();

        $matchingPath = '/users/42';
        $matchingTemplate = '/users/{id}';

        $matchPathResult = $parser->matchPath($matchingPath, $matchingTemplate);
        $tryMatchPathResult = $parser->tryMatchPath($matchingPath, $matchingTemplate);

        $this->assertSame($matchPathResult, $tryMatchPathResult);
    }

    #[Test]
    public function try_match_path_returns_null_where_match_path_throws(): void
    {
        $parser = new PathParser();

        $mismatchPath = '/users/123/posts';
        $mismatchTemplate = '/users/{id}/posts/{postId}';

        $this->expectException(PathMismatchException::class);
        $parser->matchPath($mismatchPath, $mismatchTemplate);
    }

    #[Test]
    public function try_match_path_no_exception_on_mismatch(): void
    {
        $parser = new PathParser();

        $result = $parser->tryMatchPath('/users/123/posts', '/users/{id}/posts/{postId}');

        $this->assertNull($result);
    }

    #[Test]
    public function path_finder_100_routes_memory_stable(): void
    {
        $yamlParts = ["openapi: 3.1.0\ninfo:\n  title: Test API\n  version: 1.0.0\npaths:"];

        for ($i = 0; $i < 100; $i++) {
            $yamlParts[] = sprintf(
                "  /api/v1/resource%d:\n    get:\n      responses:\n        '200':\n          description: OK",
                $i,
            );
        }

        $yaml = implode("\n", $yamlParts);

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);

        gc_collect_cycles();
        $memoryBefore = memory_get_usage();

        for ($i = 0; $i < 100; $i++) {
            $finder->findOperation(sprintf('/api/v1/resource%d', $i), 'GET');
        }

        gc_collect_cycles();
        $memoryAfter = memory_get_usage();
        $memoryGrowth = $memoryAfter - $memoryBefore;

        $this->assertLessThan(2_000_000, $memoryGrowth, 'Memory growth for 100 route lookups should be under 2MB');
    }

    #[Test]
    public function path_finding_with_base_path_100_routes(): void
    {
        $yamlParts = ["openapi: 3.1.0\ninfo:\n  title: Test API\n  version: 1.0.0\nservers:\n  - url: https://api.example.com/v1\npaths:"];

        for ($i = 0; $i < 100; $i++) {
            $yamlParts[] = sprintf(
                "  /resource%d/{id}:\n    get:\n      responses:\n        '200':\n          description: OK",
                $i,
            );
        }

        $yaml = implode("\n", $yamlParts);

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);
        $matcher = new ServerPathMatcher();
        $servers = $document->servers->servers;

        $start = microtime(true);

        for ($i = 0; $i < 50; $i++) {
            $requestPath = sprintf('/v1/resource%d/123', $i % 100);
            $match = $matcher->matchPath($servers, $requestPath);
            $matchedPath = null !== $match ? $match->strippedPath : $requestPath;
            $finder->findOperation($matchedPath, 'GET');
        }

        $timeWithStripping = microtime(true) - $start;

        $this->assertLessThan(0.15, $timeWithStripping, 'Path finding with server stripping for 100 routes over 50 iterations should be under 150ms');
    }

    #[Test]
    public function path_finding_with_base_path_no_memory_leak(): void
    {
        $yamlParts = ["openapi: 3.1.0\ninfo:\n  title: Test API\n  version: 1.0.0\nservers:\n  - url: https://api.example.com/v1\npaths:"];

        for ($i = 0; $i < 50; $i++) {
            $yamlParts[] = sprintf(
                "  /resource%d/{id}:\n    get:\n      responses:\n        '200':\n          description: OK",
                $i,
            );
        }

        $yaml = implode("\n", $yamlParts);

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $finder = new PathFinder($document);
        $matcher = new ServerPathMatcher();
        $servers = $document->servers->servers;

        gc_collect_cycles();
        $memoryBefore = memory_get_usage();

        for ($i = 0; $i < 50; $i++) {
            $requestPath = sprintf('/v1/resource%d/123', $i % 50);
            $match = $matcher->matchPath($servers, $requestPath);
            $matchedPath = null !== $match ? $match->strippedPath : $requestPath;
            $finder->findOperation($matchedPath, 'GET');
        }

        gc_collect_cycles();
        $memoryAfter = memory_get_usage();
        $memoryDelta = $memoryAfter - $memoryBefore;

        $this->assertLessThan(1_000_000, $memoryDelta, 'Memory delta for 50 iterations with server stripping should be under 1MB');
    }
}
