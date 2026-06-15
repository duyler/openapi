<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Link;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Validator\Link\LinkContext;
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

        $context = new LinkContext(body: ['id' => 42, 'name' => 'John']);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['userId' => 42], $result['parameters']);
    }

    #[Test]
    public function resolve_nested_path_from_response_body(): void
    {
        $link = new Link(
            operationId: 'getAddress',
            parameters: ['city' => '$response.body#/address/city'],
        );

        $context = new LinkContext(body: ['address' => ['city' => 'Moscow', 'street' => 'Tverskaya']]);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['city' => 'Moscow'], $result['parameters']);
    }

    #[Test]
    public function return_null_for_missing_path(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['userId' => '$response.body#/nonexistent'],
        );

        $context = new LinkContext(body: ['id' => 42]);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['userId' => null], $result['parameters']);
    }

    #[Test]
    public function preserve_static_values(): void
    {
        $link = new Link(
            operationId: 'search',
            parameters: ['type' => 'user'],
        );

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['type' => 'user'], $result['parameters']);
    }

    #[Test]
    public function resolve_request_body_from_response(): void
    {
        $link = new Link(
            operationId: 'updateUser',
        );

        $context = new LinkContext(body: ['id' => 42]);

        $result = $this->resolver->resolve($link, $context);

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

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame($server, $result['server']);
    }

    #[Test]
    public function return_empty_parameters_when_null(): void
    {
        $link = new Link(
            operationId: 'getUser',
        );

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

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

        $body = ['id' => 42, 'name' => 'John'];
        $context = new LinkContext(body: $body);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['data' => $body], $result['parameters']);
    }

    #[Test]
    public function resolve_response_header_by_path(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['requestId' => '$response.header#/X-Request-Id'],
        );

        $context = new LinkContext(
            headers: ['X-Request-Id' => 'abc-123', 'Content-Type' => 'application/json'],
        );

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['requestId' => 'abc-123'], $result['parameters']);
    }

    #[Test]
    public function resolve_response_header_without_path(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['headers' => '$response.header'],
        );

        $headers = ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc-123'];
        $context = new LinkContext(headers: $headers);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame($headers, $result['parameters']['headers']);
    }

    #[Test]
    public function return_null_for_missing_header(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['traceId' => '$response.header#/X-Trace-Id'],
        );

        $context = new LinkContext(headers: ['Content-Type' => 'application/json']);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['traceId' => null], $result['parameters']);
    }

    #[Test]
    public function resolve_response_query_by_path(): void
    {
        $link = new Link(
            operationId: 'search',
            parameters: ['page' => '$response.query#/page'],
        );

        $context = new LinkContext(
            queryParams: ['page' => '2', 'limit' => '10'],
        );

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['page' => '2'], $result['parameters']);
    }

    #[Test]
    public function resolve_response_query_without_path(): void
    {
        $link = new Link(
            operationId: 'search',
            parameters: ['query' => '$response.query'],
        );

        $queryParams = ['page' => '1', 'sort' => 'name'];
        $context = new LinkContext(queryParams: $queryParams);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame($queryParams, $result['parameters']['query']);
    }

    #[Test]
    public function return_null_for_missing_query_param(): void
    {
        $link = new Link(
            operationId: 'search',
            parameters: ['filter' => '$response.query#/filter'],
        );

        $context = new LinkContext(queryParams: ['page' => '1']);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['filter' => null], $result['parameters']);
    }

    #[Test]
    public function resolve_url_variable(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['source' => '$url'],
        );

        $context = new LinkContext(url: 'https://api.example.com/users/42');

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['source' => 'https://api.example.com/users/42'], $result['parameters']);
    }

    #[Test]
    public function resolve_method_variable(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['verb' => '$method'],
        );

        $context = new LinkContext(method: 'POST');

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['verb' => 'POST'], $result['parameters']);
    }

    #[Test]
    public function resolve_status_code_variable(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['code' => '$statusCode'],
        );

        $context = new LinkContext(statusCode: 201);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['code' => 201], $result['parameters']);
    }

    #[Test]
    public function return_empty_string_for_unset_url(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['source' => '$url'],
        );

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['source' => ''], $result['parameters']);
    }

    #[Test]
    public function return_zero_for_unset_status_code(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['code' => '$statusCode'],
        );

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['code' => 0], $result['parameters']);
    }

    #[Test]
    public function return_unknown_expression_as_is(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['expr' => '$unknown.variable'],
        );

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['expr' => '$unknown.variable'], $result['parameters']);
    }

    #[Test]
    public function return_plain_string_without_dollar_as_is(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: ['name' => 'static-value'],
        );

        $context = new LinkContext();

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['name' => 'static-value'], $result['parameters']);
    }

    #[Test]
    public function resolve_mixed_expressions_in_single_link(): void
    {
        $link = new Link(
            operationId: 'getUser',
            parameters: [
                'userId' => '$response.body#/id',
                'requestId' => '$response.header#/X-Request-Id',
                'page' => '$response.query#/page',
                'source' => '$url',
                'method' => '$method',
                'status' => '$statusCode',
                'type' => 'user',
            ],
        );

        $context = new LinkContext(
            body: ['id' => 42, 'name' => 'John'],
            headers: ['X-Request-Id' => 'req-789'],
            queryParams: ['page' => '3'],
            url: 'https://api.example.com/search',
            method: 'GET',
            statusCode: 200,
        );

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(42, $result['parameters']['userId']);
        $this->assertSame('req-789', $result['parameters']['requestId']);
        $this->assertSame('3', $result['parameters']['page']);
        $this->assertSame('https://api.example.com/search', $result['parameters']['source']);
        $this->assertSame('GET', $result['parameters']['method']);
        $this->assertSame(200, $result['parameters']['status']);
        $this->assertSame('user', $result['parameters']['type']);
    }

    #[Test]
    public function resolve_deep_seven_level_nested_path_from_response_body(): void
    {
        $link = new Link(
            operationId: 'getDeepValue',
            parameters: ['target' => '$response.body#/a/b/c/d/e/f/g'],
        );

        $context = new LinkContext(body: [
            'a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'deep_value']]]]]],
        ]);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame('deep_value', $result['parameters']['target']);
    }

    #[Test]
    public function resolve_intermediate_segment_of_deep_nested_path(): void
    {
        $link = new Link(
            operationId: 'getIntermediate',
            parameters: ['middle' => '$response.body#/a/b/c'],
        );

        $context = new LinkContext(body: [
            'a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'deep_value']]]]]],
        ]);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['d' => ['e' => ['f' => ['g' => 'deep_value']]]], $result['parameters']['middle']);
    }

    #[Test]
    public function return_null_when_deep_path_segment_does_not_exist(): void
    {
        $link = new Link(
            operationId: 'getMissing',
            parameters: ['target' => '$response.body#/a/b/c/missing/deeper'],
        );

        $context = new LinkContext(body: [
            'a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'deep_value']]]]]],
        ]);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['target' => null], $result['parameters']);
    }

    #[Test]
    public function return_null_when_deep_path_targets_non_array_intermediate(): void
    {
        $link = new Link(
            operationId: 'getThroughScalar',
            parameters: ['target' => '$response.body#/a/b/c/d/e/f/g'],
        );

        $context = new LinkContext(body: [
            'a' => ['b' => ['c' => 'scalar_value']],
        ]);

        $result = $this->resolver->resolve($link, $context);

        $this->assertSame(['target' => null], $result['parameters']);
    }
}
