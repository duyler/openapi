<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use Duyler\OpenApi\Validator\Request\PathParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PathParserTest extends TestCase
{
    private PathParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PathParser();
    }

    #[Test]
    public function extract_parameters_from_template(): void
    {
        $params = $this->parser->parseParameters('/users/{id}');

        $this->assertSame(['id'], $params);
    }

    #[Test]
    public function extract_multiple_parameters(): void
    {
        $params = $this->parser->parseParameters('/users/{userId}/posts/{postId}');

        $this->assertSame(['userId', 'postId'], $params);
    }

    #[Test]
    public function match_simple_path(): void
    {
        $result = $this->parser->matchPath('/users/123', '/users/{id}');

        $this->assertSame(['id' => '123'], $result);
    }

    #[Test]
    public function match_nested_path(): void
    {
        $result = $this->parser->matchPath('/users/123/posts/456', '/users/{userId}/posts/{postId}');

        $this->assertSame(['userId' => '123', 'postId' => '456'], $result);
    }

    #[Test]
    public function throw_error_for_mismatch(): void
    {
        $this->expectException(PathMismatchException::class);
        $this->expectExceptionMessage('Path "/users/123/posts" does not match template "/users/{id}/posts/{postId}"');

        $this->parser->matchPath('/users/123/posts', '/users/{id}/posts/{postId}');
    }
}
