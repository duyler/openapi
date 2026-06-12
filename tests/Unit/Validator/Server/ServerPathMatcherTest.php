<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Validator\Server\ServerPathMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerPathMatcherTest extends TestCase
{
    private ServerPathMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new ServerPathMatcher();
    }

    #[Test]
    public function strips_base_path_from_request_path(): void
    {
        $server = new Server(url: '/v1');

        $result = $this->matcher->matchPath([$server], '/v1/users/42');

        $this->assertNotNull($result);
        $this->assertSame('/users/42', $result->strippedPath);
        $this->assertSame($server, $result->matchedServer);
    }

    #[Test]
    public function strips_path_from_full_server_url(): void
    {
        $server = new Server(url: 'https://api.example.com/v1');

        $result = $this->matcher->matchPath([$server], '/v1/users/42');

        $this->assertNotNull($result);
        $this->assertSame('/users/42', $result->strippedPath);
    }

    #[Test]
    public function returns_null_when_no_base_path_in_server(): void
    {
        $server = new Server(url: 'https://api.example.com');

        $result = $this->matcher->matchPath([$server], '/users/42');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_server_is_root(): void
    {
        $server = new Server(url: '/');

        $result = $this->matcher->matchPath([$server], '/users/42');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_request_does_not_match(): void
    {
        $server = new Server(url: '/v2');

        $result = $this->matcher->matchPath([$server], '/v1/users/42');

        $this->assertNull($result);
    }

    #[Test]
    public function handles_multiple_servers_longest_prefix_match(): void
    {
        $serverV1 = new Server(url: '/v1');
        $serverV1Beta = new Server(url: '/v1beta');

        $result = $this->matcher->matchPath([$serverV1, $serverV1Beta], '/v1beta/users');

        $this->assertNotNull($result);
        $this->assertSame('/users', $result->strippedPath);
        $this->assertSame($serverV1Beta, $result->matchedServer);
    }

    #[Test]
    public function longest_prefix_match_with_variables(): void
    {
        $serverA = new Server(
            url: 'https://api.example.com/{ver}',
            variables: [
                'ver' => ['default' => 'v1'],
            ],
        );
        $serverB = new Server(url: 'https://api.example.com/v1/sub');

        $result = $this->matcher->matchPath([$serverA, $serverB], '/v1/sub/resource');

        $this->assertNotNull($result);
        $this->assertSame('/resource', $result->strippedPath);
        $this->assertSame($serverB, $result->matchedServer);
    }

    #[Test]
    public function handles_exact_match_base_path(): void
    {
        $server = new Server(url: '/v1');

        $result = $this->matcher->matchPath([$server], '/v1');

        $this->assertNotNull($result);
        $this->assertSame('/', $result->strippedPath);
    }

    #[Test]
    public function handles_server_variables(): void
    {
        $server = new Server(
            url: 'https://{env}.example.com/{version}',
            variables: [
                'env' => ['default' => 'prod'],
                'version' => ['default' => 'v2'],
            ],
        );

        $result = $this->matcher->matchPath([$server], '/v2/users');

        $this->assertNotNull($result);
        $this->assertSame('/users', $result->strippedPath);
    }

    #[Test]
    public function skips_server_with_unresolvable_variables(): void
    {
        $server = new Server(url: 'https://{unknown}.example.com/v1');

        $result = $this->matcher->matchPath([$server], '/v1/users');

        $this->assertNull($result);
    }

    #[Test]
    public function handles_relative_server_url(): void
    {
        $server = new Server(url: './v1');

        $result = $this->matcher->matchPath([$server], '/v1/users/42');

        $this->assertNotNull($result);
        $this->assertSame('/users/42', $result->strippedPath);
    }
}
