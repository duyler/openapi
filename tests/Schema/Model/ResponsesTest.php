<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Responses
 */
final class ResponsesTest extends TestCase
{
    #[Test]
    public function can_create_responses(): void
    {
        $response = new Response(
            description: 'Success',
            headers: null,
            content: null,
        );

        $responses = new Responses(
            responses: ['200' => $response],
        );

        self::assertArrayHasKey('200', $responses->responses);
        self::assertInstanceOf(Response::class, $responses->responses['200']);
    }

    #[Test]
    public function can_create_empty_responses(): void
    {
        $responses = new Responses(
            responses: [],
        );

        self::assertCount(0, $responses->responses);
    }

    #[Test]
    public function json_serialize_includes_responses(): void
    {
        $response = new Response(
            description: 'Success',
            headers: null,
            content: null,
        );

        $responses = new Responses(
            responses: ['200' => $response],
        );

        $serialized = $responses->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('200', $serialized);
        self::assertIsArray($serialized['200']);
        self::assertArrayHasKey('description', $serialized['200']);
    }
}
