<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class StreamingEdgeCaseTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function stream_with_all_valid_items_passes_validation_without_parse_warnings(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->withLogger($logger)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"first"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:01Z","level":"warn","message":"second"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:02Z","level":"error","message":"third"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        $this->assertSame('/logs', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function stream_with_invalid_item_at_third_position_throws_enum_error_with_expected_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"first"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:01Z","level":"warn","message":"second"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:02Z","level":"unexpected","message":"third"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:03Z","level":"error","message":"fourth"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        try {
            $validator->validateResponse($response, $operation);
            $this->fail('Expected EnumError was not thrown for invalid enum value at third stream item');
        } catch (EnumError $error) {
            $this->assertSame('enum', $error->keyword());
            $this->assertSame('/level', $error->dataPath());
            $this->assertSame('unexpected', $error->params()['actual']);
            $this->assertStringContainsString('unexpected', $error->message());
        }
    }

    #[Test]
    public function stream_with_invalid_item_at_first_position_throws_enum_error_immediately(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"not-allowed","message":"first"}' . "\n"
            . '{"timestamp":"2024-01-01T00:00:01Z","level":"warn","message":"second"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        try {
            $validator->validateResponse($response, $operation);
            $this->fail('Expected EnumError was not thrown for invalid enum value at first stream item');
        } catch (EnumError $error) {
            $this->assertSame('/level', $error->dataPath());
            $this->assertSame('not-allowed', $error->params()['actual']);
            $this->assertSame(['debug', 'info', 'warn', 'error'], $error->params()['allowed']);
        }
    }

    #[Test]
    public function stream_with_invalid_json_item_logs_single_warning_and_skips_validation_for_that_item(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('NDJSON'),
                $this->callback(static fn(array $context): bool => isset($context['line'], $context['exception'])),
            );

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->withLogger($logger)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"first"}' . "\n"
            . 'this-is-not-json' . "\n"
            . '{"timestamp":"2024-01-01T00:00:02Z","level":"error","message":"third"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        $this->assertSame('/logs', $operation->path);
    }

    #[Test]
    public function sse_stream_with_invalid_item_data_at_second_position_throws_type_mismatch_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../../fixtures/response-validation-specs/streaming.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/events');
        $operation = $validator->validateRequest($request);

        $body = "event: message\n"
            . "data: {\"message\":\"hello\",\"count\":1}\n\n"
            . "event: update\n"
            . "data: {\"message\":\"world\",\"count\":\"not-an-integer\"}\n\n";

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withBody($this->psrFactory->createStream($body));

        try {
            $validator->validateResponse($response, $operation);
            $this->fail('Expected TypeMismatchError was not thrown for invalid integer type at second SSE event');
        } catch (TypeMismatchError $error) {
            $this->assertSame('type', $error->keyword());
            $this->assertSame('/data/count', $error->dataPath());
            $this->assertSame('integer', $error->params()['expected']);
            $this->assertSame('string', $error->params()['actual']);
        }
    }
}
