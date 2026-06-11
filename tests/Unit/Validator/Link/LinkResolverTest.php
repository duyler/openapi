<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Link;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LinkResolverTest extends TestCase
{
    private LinkResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LinkResolver();
    }

    #[Test]
    public function resolve_parameters_from_response_body(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['userId' => '$response.body#/id'],
        );

        $result = $this->resolver->resolve($link, ['id' => 42, 'name' => 'John']);

        $this->assertSame(['userId' => 42], $result['parameters']);
    }

    #[Test]
    public function resolve_nested_path_from_response_body(): void
    {
        $link = new Link(
            operationId: 'getAddress',
            parameters: ['city' => '$response.body#/address/city'],
        );

        $responseData = ['address' => ['city' => 'Moscow', 'street' => 'Tverskaya']];

        $result = $this->resolver->resolve($link, $responseData);

        $this->assertSame(['city' => 'Moscow'], $result['parameters']);
    }

    #[Test]
    public function return_null_for_missing_path(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['userId' => '$response.body#/nonexistent'],
        );

        $result = $this->resolver->resolve($link, ['id' => 42]);

        $this->assertSame(['userId' => null], $result['parameters']);
    }

    #[Test]
    public function preserve_static_values(): void
    {
        $link = new Link(
            operationId: 'search',
            parameters: ['type' => 'user'],
        );

        $result = $this->resolver->resolve($link, []);

        $this->assertSame(['type' => 'user'], $result['parameters']);
    }

    #[Test]
    public function resolve_request_body_from_response(): void
    {
        $link = new Link(
            operationId: 'updateUser',
        );

        $result = $this->resolver->resolve($link, ['id' => 42]);

        $this->assertNull($result['requestBody']);
    }

    #[Test]
    public function preserve_server(): void
    {
        $server = new Server(url: 'https://api.example.com');

        $link = new Link(
            operationId: 'getUser',
            server: $server,
        );

        $result = $this->resolver->resolve($link, []);

        $this->assertSame($server, $result['server']);
    }

    #[Test]
    public function return_empty_parameters_when_null(): void
    {
        $link = new Link(
            operationId: 'getUser',
        );

        $result = $this->resolver->resolve($link, []);

        $this->assertSame([], $result['parameters']);
        $this->assertNull($result['requestBody']);
        $this->assertNull($result['server']);
    }

    #[Test]
    public function return_full_body_when_no_path(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['data' => '$response.body'],
        );

        $responseData = ['id' => 42, 'name' => 'John'];

        $result = $this->resolver->resolve($link, $responseData);

        $this->assertSame(['data' => $responseData], $result['parameters']);
    }

    #[Test]
    public function preserve_non_expression_values(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['query' => '$response.query', 'header' => '$response.header'],
        );

        $result = $this->resolver->resolve($link, []);

        $this->assertSame('$response.query', $result['parameters']['query']);
        $this->assertSame('$response.header', $result['parameters']['header']);
    }
}
