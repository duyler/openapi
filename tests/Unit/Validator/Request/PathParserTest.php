<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/** @internal */
final class PathParserTest extends TestCase
{
    private PathParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PathParser(new PathRegexCache());
    }

    #[Test]
    public function match_simple_path(): void
    {
        $result = $this->parser->matchPath('/users/123', '/users/{id}');

        $this->assertSame(['id' => '123'], $result);
    }

    #[Test]
    public function match_path_without_parameters(): void
    {
        $result = $this->parser->matchPath('/users', '/users');

        $this->assertSame([], $result);
    }

    #[Test]
    public function match_path_with_trailing_slash(): void
    {
        $result = $this->parser->matchPath('/users/123/', '/users/{id}/');

        $this->assertSame(['id' => '123'], $result);
    }

    #[Test]
    public function match_root_path(): void
    {
        $result = $this->parser->matchPath('/', '/');

        $this->assertSame([], $result);
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
        $this->expectExceptionMessage('Request path does not match any declared template');

        $this->parser->matchPath('/users/123/posts', '/users/{id}/posts/{postId}');
    }

    #[Test]
    public function throw_error_for_extra_segments(): void
    {
        $this->expectException(PathMismatchException::class);

        $this->parser->matchPath('/users/123/posts/456/comments', '/users/{id}/posts/{postId}');
    }

    #[Test]
    public function throw_error_for_missing_segments(): void
    {
        $this->expectException(PathMismatchException::class);

        $this->parser->matchPath('/users/123', '/users/{id}/posts/{postId}');
    }

    #[Test]
    public function match_path_with_alphanumeric_values(): void
    {
        $result = $this->parser->matchPath('/users/abc-123_def/posts/xyz-456', '/users/{userId}/posts/{postId}');

        $this->assertSame(['userId' => 'abc-123_def', 'postId' => 'xyz-456'], $result);
    }

    #[Test]
    public function match_path_with_encoded_values(): void
    {
        $result = $this->parser->matchPath('/users/user%20name', '/users/{userName}');

        $this->assertSame(['userName' => 'user%20name'], $result);
    }

    #[Test]
    public function try_match_path_with_preg_executor_returns_null_on_non_matching_template(): void
    {
        $parser = new PathParser(new PathRegexCache(), new PregExecutor(maxBacktracks: 1000));
        $attackerPath = '/users/' . str_repeat('a', 50) . '!/extra-segment';

        $this->assertNull($parser->tryMatchPath($attackerPath, '/users/{id}'));
    }

    #[Test]
    public function constructor_accepts_preg_executor_argument(): void
    {
        $parser = new PathParser(new PathRegexCache(), new PregExecutor(maxBacktracks: 500));

        $this->assertSame(['id' => '42'], $parser->matchPath('/users/42', '/users/{id}'));
    }
}
