<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

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
    public function parse_simple_path_without_parameters(): void
    {
        $params = $this->parser->parseParameters('/users');

        $this->assertSame([], $params);
    }

    #[Test]
    public function parse_path_with_single_parameter(): void
    {
        $params = $this->parser->parseParameters('/users/{id}');

        $this->assertSame(['id'], $params);
    }

    #[Test]
    public function parse_path_with_multiple_parameters(): void
    {
        $params = $this->parser->parseParameters('/users/{userId}/posts/{postId}/comments/{commentId}');

        $this->assertSame(['userId', 'postId', 'commentId'], $params);
    }

    #[Test]
    public function parse_path_with_nested_parameters(): void
    {
        $params = $this->parser->parseParameters('/api/v1/users/{userId}/profile/{profileId}/settings/{settingId}');

        $this->assertSame(['userId', 'profileId', 'settingId'], $params);
    }

    #[Test]
    public function parse_path_with_trailing_slash(): void
    {
        $params = $this->parser->parseParameters('/users/{id}/');

        $this->assertSame(['id'], $params);
    }

    #[Test]
    public function parse_root_path(): void
    {
        $params = $this->parser->parseParameters('/');

        $this->assertSame([], $params);
    }

    #[Test]
    public function extract_parameters_from_path_template(): void
    {
        $params = $this->parser->parseParameters('/users/{userId}/posts/{postId}');

        $this->assertIsArray($params);
        $this->assertContains('userId', $params);
        $this->assertContains('postId', $params);
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
        $this->expectExceptionMessage('Path "/users/123/posts" does not match template "/users/{id}/posts/{postId}"');

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
}
