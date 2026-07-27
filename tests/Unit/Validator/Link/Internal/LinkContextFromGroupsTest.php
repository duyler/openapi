<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Link\Internal;

use Duyler\OpenApi\Validator\Link\Internal\RequestScope;
use Duyler\OpenApi\Validator\Link\Internal\ResponseScope;
use Duyler\OpenApi\Validator\Link\LinkContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestScope::class)]
#[CoversClass(ResponseScope::class)]
#[CoversClass(LinkContext::class)]
final class LinkContextFromGroupsTest extends TestCase
{
    #[Test]
    public function from_groups_distributes_request_and_response_fields(): void
    {
        $request = new RequestScope(
            url: 'https://api.example.com/users/42',
            method: 'GET',
            pathParams: ['userId' => 42],
            requestHeaders: ['X-Request-Id' => 'req-789'],
            requestBody: ['extra' => 'payload'],
        );
        $response = new ResponseScope(
            body: ['id' => 42, 'name' => 'John'],
            headers: ['X-Total' => '1'],
            queryParams: ['page' => 1],
            statusCode: 200,
        );

        $context = LinkContext::fromGroups($request, $response);

        // Response scope
        self::assertSame(['id' => 42, 'name' => 'John'], $context->body);
        self::assertSame(['X-Total' => '1'], $context->headers);
        self::assertSame(['page' => 1], $context->queryParams);
        self::assertSame(200, $context->statusCode);

        // Request scope
        self::assertSame('https://api.example.com/users/42', $context->url);
        self::assertSame('GET', $context->method);
        self::assertSame(['userId' => 42], $context->pathParams);
        self::assertSame(['X-Request-Id' => 'req-789'], $context->requestHeaders);
        self::assertSame(['extra' => 'payload'], $context->requestBody);
    }

    #[Test]
    public function from_groups_defaults_match_constructor_defaults_when_omitted(): void
    {
        $context = LinkContext::fromGroups(new RequestScope(), new ResponseScope());

        self::assertSame([], $context->body);
        self::assertSame([], $context->headers);
        self::assertSame([], $context->queryParams);
        self::assertSame('', $context->url);
        self::assertSame('', $context->method);
        self::assertSame(0, $context->statusCode);
        self::assertSame([], $context->pathParams);
        self::assertSame([], $context->requestHeaders);
        self::assertNull($context->requestBody);
    }

    #[Test]
    public function from_groups_is_equivalent_to_constructor_named_args(): void
    {
        $request = new RequestScope(
            url: 'https://example.com',
            method: 'POST',
            pathParams: ['k' => 'v'],
        );
        $response = new ResponseScope(body: ['a' => 1], statusCode: 201);

        $viaGroups = LinkContext::fromGroups($request, $response);
        $viaCtor = new LinkContext(
            body: ['a' => 1],
            statusCode: 201,
            url: 'https://example.com',
            method: 'POST',
            pathParams: ['k' => 'v'],
        );

        // Equivalent across all fields (queryParams empty-by-default in both)
        self::assertSame($viaCtor->body, $viaGroups->body);
        self::assertSame($viaCtor->statusCode, $viaGroups->statusCode);
        self::assertSame($viaCtor->url, $viaGroups->url);
        self::assertSame($viaCtor->method, $viaGroups->method);
        self::assertSame($viaCtor->pathParams, $viaGroups->pathParams);
    }
}
