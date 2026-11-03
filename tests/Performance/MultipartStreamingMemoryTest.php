<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\BodyReader;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function gc_collect_cycles;
use function hrtime;
use function memory_get_peak_usage;
use function memory_get_usage;
use function sprintf;
use function str_repeat;
use function strlen;

final class MultipartStreamingMemoryTest extends TestCase
{
    private const int PART_BYTES = 128 * 1024;

    private const int PART_COUNT = 40;

    private const int BODY_BUDGET_BYTES = 4 * 1024 * 1024;

    private const string STREAMING_IMAGES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Streaming Multipart Memory
  version: 1.0.0
paths:
  /images:
    post:
      operationId: uploadImages
      requestBody:
        required: true
        content:
          multipart/mixed:
            itemSchema:
              $comment: A single data image from the device
            itemEncoding:
              contentType: image/jpg
      responses:
        '200':
          description: Uploaded
YAML;

    private const string NESTED_STREAMING_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Nested Streaming Multipart Memory
  version: 1.0.0
paths:
  /bundle:
    post:
      operationId: uploadBundle
      requestBody:
        required: true
        content:
          multipart/mixed:
            schema:
              type: array
              prefixItems:
              - type: array
              - type: string
            prefixEncoding:
            - contentType: multipart/mixed
              prefixEncoding:
              - contentType: text/plain
              - contentType: image/png
            - {}
      responses:
        '200':
          description: Uploaded
YAML;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function streaming_validation_stays_within_the_memory_budget(): void
    {
        $validator = $this->buildStreamingValidator();
        $request = $this->createImagesRequest();

        gc_collect_cycles();
        $baseline = memory_get_peak_usage(true);

        $validator->validateRequest($request);

        gc_collect_cycles();
        $peakGrowth = memory_get_peak_usage(true) - $baseline;

        self::assertLessThan(
            self::BODY_BUDGET_BYTES,
            $peakGrowth,
            sprintf('Streaming peak growth of %d bytes must stay under the %d byte body budget', $peakGrowth, self::BODY_BUDGET_BYTES),
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function buffering_the_whole_stream_exceeds_the_budget(): void
    {
        $request = $this->createImagesRequest();

        $this->expectException(BodyTooLargeException::class);

        BodyReader::readSafely($request->getBody(), self::BODY_BUDGET_BYTES);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function buffered_payload_occupies_more_than_the_streaming_budget(): void
    {
        $request = $this->createImagesRequest();

        gc_collect_cycles();
        $before = memory_get_usage(true);

        $buffered = $request->getBody()->__toString();

        $held = memory_get_usage(true) - $before;

        self::assertGreaterThan(self::PART_BYTES, strlen($buffered));
        self::assertGreaterThan(self::BODY_BUDGET_BYTES, $held);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function nested_streaming_is_processed_within_the_memory_budget(): void
    {
        $validator = $this->buildNestedValidator();
        $request = $this->createNestedRequest();

        gc_collect_cycles();
        $baseline = memory_get_peak_usage(true);
        $start = hrtime(true);

        $validator->validateRequest($request);

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        gc_collect_cycles();
        $peakGrowth = memory_get_peak_usage(true) - $baseline;

        self::assertLessThan(
            self::BODY_BUDGET_BYTES,
            $peakGrowth,
            sprintf('Nested streaming peak growth of %d bytes must stay under the %d byte budget', $peakGrowth, self::BODY_BUDGET_BYTES),
        );
        self::assertGreaterThan(0.0, $elapsedMs);
    }

    private function buildStreamingValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STREAMING_IMAGES_SPEC)
            ->withMaxMultipartBodySize(self::BODY_BUDGET_BYTES)
            ->build();
    }

    private function buildNestedValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NESTED_STREAMING_SPEC)
            ->withMaxMultipartBodySize(self::BODY_BUDGET_BYTES)
            ->build();
    }

    private function createImagesRequest(): ServerRequestInterface
    {
        $part = "\xFF\xD8\xFF" . str_repeat('j', self::PART_BYTES - 3);
        $body = '';

        for ($index = 0; $index < self::PART_COUNT; ++$index) {
            $body .= "--stream\r\nContent-Type: image/jpg\r\n\r\n{$part}\r\n";
        }

        $body .= '--stream--';

        return $this->factory->createServerRequest('POST', '/images')
            ->withHeader('Content-Type', 'multipart/mixed; boundary=stream')
            ->withBody($this->factory->createStream($body));
    }

    private function createNestedRequest(): ServerRequestInterface
    {
        $pngPart = "\x89PNG" . str_repeat('p', self::PART_BYTES - 4);
        $inner = "--inner\r\nContent-Type: text/plain\r\n\r\nhello\r\n"
            . "--inner\r\nContent-Type: image/png\r\n\r\n{$pngPart}\r\n"
            . '--inner--';

        $body = "--stream\r\nContent-Type: multipart/mixed; boundary=inner\r\n\r\n{$inner}\r\n"
            . "--stream\r\nContent-Type: text/plain\r\n\r\ntail\r\n"
            . '--stream--';

        return $this->factory->createServerRequest('POST', '/bundle')
            ->withHeader('Content-Type', 'multipart/mixed; boundary=stream')
            ->withBody($this->factory->createStream($body));
    }
}
