<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Parser\Internal\ComponentTreeBuilder;
use Duyler\OpenApi\Schema\Parser\Internal\ResponseTreeBuilder;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseTreeBuilder::class)]
final class ResponseTreeBuilderTest extends TestCase
{
    private ResponseTreeBuilder $builder;
    private OpenApiBuildContext $context;

    protected function setUp(): void
    {
        $this->context = new OpenApiBuildContext();
        $this->builder = new ResponseTreeBuilder($this->context, new ComponentTreeBuilder($this->context));
    }

    #[Test]
    public function build_responses_returns_status_code_map(): void
    {
        $responses = $this->builder->buildResponses([
            '200' => ['description' => 'OK'],
            '404' => ['description' => 'Not Found'],
        ]);

        self::assertInstanceOf(Responses::class, $responses);
        self::assertInstanceOf(Response::class, $responses->responses['200']);
        self::assertSame('Not Found', $responses->responses['404']->description);
    }

    #[Test]
    public function build_response_by_ref(): void
    {
        $response = $this->builder->buildResponse([
            '$ref' => '#/components/responses/NotFound',
            'summary' => 'Ref summary',
            'description' => 'Ref description',
        ]);

        self::assertSame('#/components/responses/NotFound', $response->ref);
        self::assertSame('Ref summary', $response->refSummary);
        self::assertSame('Ref description', $response->refDescription);
    }

    #[Test]
    public function build_response_full_object_without_ref(): void
    {
        $response = $this->builder->buildResponse([
            'description' => 'OK',
            'headers' => ['X-Trace' => ['description' => 'trace']],
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
            'links' => ['UserLink' => ['operationId' => 'getUser']],
        ]);

        self::assertNull($response->ref);
        self::assertSame('OK', $response->description);
        self::assertInstanceOf(Headers::class, $response->headers);
        self::assertInstanceOf(Content::class, $response->content);
        self::assertInstanceOf(Links::class, $response->links);
    }

    #[Test]
    public function build_link_with_operation_id(): void
    {
        $link = $this->builder->buildLink([
            'operationId' => 'getUserById',
            'parameters' => ['id' => '$response.body#/id'],
            'description' => 'link to user',
        ]);

        self::assertInstanceOf(Link::class, $link);
        self::assertSame('getUserById', $link->operationId);
        self::assertSame('link to user', $link->description);
        self::assertSame(['id' => '$response.body#/id'], $link->parameters);
    }

    #[Test]
    public function build_callbacks_map_returns_nested_path_items(): void
    {
        $callbacks = $this->builder->buildCallbacksMap([
            'myCallback' => [
                '{$request.body#/callback_url}' => [
                    'post' => ['responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ]);

        self::assertInstanceOf(Callbacks::class, $callbacks);
    }
}
