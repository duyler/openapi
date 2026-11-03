<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Link;

use Duyler\OpenApi\Validator\Link\Internal\ExpressionData;
use Duyler\OpenApi\Validator\Link\Internal\RuntimeExpressionEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeExpressionEvaluatorTest extends TestCase
{
    private RuntimeExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new RuntimeExpressionEvaluator();
    }

    #[Test]
    #[DataProvider('expression_resolution_provider')]
    public function expression_resolves_to_expected_value(string $expression, ExpressionData $data, mixed $expected): void
    {
        $resolved = $this->evaluator->resolve($expression, $data);

        self::assertSame($expected, $resolved);
    }

    public static function expression_resolution_provider(): array
    {
        $data = new ExpressionData(
            url: 'https://api.example.com/items',
            method: 'POST',
            statusCode: 201,
            requestBody: ['id' => 'e7', 'nested' => ['deep' => ['value' => 42]]],
            requestHeaders: ['x-request-id' => 'req-1', 'accept' => ['a', 'b']],
            queryParams: ['tag' => 'done'],
            pathParams: ['itemId' => 'i9'],
            body: ['id' => 'r1'],
            headers: ['x-response-id' => 'res-1'],
        );

        return [
            '$url' => ['$url', $data, 'https://api.example.com/items'],
            '$method' => ['$method', $data, 'POST'],
            '$statusCode' => ['$statusCode', $data, 201],
            '$request.path.itemId' => ['$request.path.itemId', $data, 'i9'],
            '$request.path.unknown' => ['$request.path.unknown', $data, null],
            '$request.query.tag' => ['$request.query.tag', $data, 'done'],
            '$request.query.unknown' => ['$request.query.unknown', $data, null],
            '$request.header.X-Request-Id' => ['$request.header.X-Request-Id', $data, 'req-1'],
            '$request.header.x-request-id' => ['$request.header.x-request-id', $data, 'req-1'],
            '$request.header.list-joins-with-comma' => ['$request.header.Accept', $data, 'a,b'],
            '$request.header.unknown' => ['$request.header.unknown', $data, null],
            '$request.body' => ['$request.body', $data, ['id' => 'e7', 'nested' => ['deep' => ['value' => 42]]]],
            '$request.body#/id' => ['$request.body#/id', $data, 'e7'],
            '$request.body#/nested/deep/value' => ['$request.body#/nested/deep/value', $data, 42],
            '$request.body#/unknown' => ['$request.body#/unknown', $data, null],
            '$response.body#/id' => ['$response.body#/id', $data, 'r1'],
            '$response.header.x-response-id' => ['$response.header.x-response-id', $data, 'res-1'],
            '$response.query.tag' => ['$response.query.tag', $data, 'done'],
            'unknown expression resolves to literal' => ['$foo', $data, '$foo'],
            'escaped tilde pointer' => ['$request.body#/a~1b', new ExpressionData(requestBody: ['a/b' => 'v']), 'v'],
        ];
    }

    #[Test]
    public function status_code_without_response_context_resolves_to_null(): void
    {
        $resolved = $this->evaluator->resolve('$statusCode', new ExpressionData());

        self::assertNull($resolved);
    }

    #[Test]
    public function response_body_pointer_without_response_context_resolves_to_null(): void
    {
        $resolved = $this->evaluator->resolve('$response.body#/id', new ExpressionData());

        self::assertNull($resolved);
    }

    #[Test]
    public function response_header_without_response_context_resolves_to_null(): void
    {
        $resolved = $this->evaluator->resolve('$response.header.x', new ExpressionData());

        self::assertNull($resolved);
    }
}
