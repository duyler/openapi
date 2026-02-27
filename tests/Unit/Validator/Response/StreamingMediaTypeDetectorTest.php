<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Response\StreamingMediaTypeDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamingMediaTypeDetector::class)]
final class StreamingMediaTypeDetectorTest extends TestCase
{
    #[Test]
    public function detects_text_event_stream(): void
    {
        self::assertTrue(StreamingMediaTypeDetector::isStreaming('text/event-stream'));
    }

    #[Test]
    public function detects_application_jsonl(): void
    {
        self::assertTrue(StreamingMediaTypeDetector::isStreaming('application/jsonl'));
    }

    #[Test]
    public function detects_application_x_ndjson(): void
    {
        self::assertTrue(StreamingMediaTypeDetector::isStreaming('application/x-ndjson'));
    }

    #[Test]
    public function detects_application_json_seq(): void
    {
        self::assertTrue(StreamingMediaTypeDetector::isStreaming('application/json-seq'));
    }

    #[Test]
    public function detects_streaming_with_charset(): void
    {
        self::assertTrue(StreamingMediaTypeDetector::isStreaming('text/event-stream; charset=utf-8'));
    }

    #[Test]
    public function detects_streaming_with_boundary(): void
    {
        self::assertTrue(StreamingMediaTypeDetector::isStreaming('application/jsonl; boundary=frame'));
    }

    #[Test]
    public function rejects_regular_json(): void
    {
        self::assertFalse(StreamingMediaTypeDetector::isStreaming('application/json'));
    }

    #[Test]
    public function rejects_regular_xml(): void
    {
        self::assertFalse(StreamingMediaTypeDetector::isStreaming('application/xml'));
    }

    #[Test]
    public function rejects_html(): void
    {
        self::assertFalse(StreamingMediaTypeDetector::isStreaming('text/html'));
    }

    #[Test]
    public function rejects_plain_text(): void
    {
        self::assertFalse(StreamingMediaTypeDetector::isStreaming('text/plain'));
    }

    #[Test]
    public function rejects_form_urlencoded(): void
    {
        self::assertFalse(StreamingMediaTypeDetector::isStreaming('application/x-www-form-urlencoded'));
    }

    #[Test]
    public function rejects_octet_stream(): void
    {
        self::assertFalse(StreamingMediaTypeDetector::isStreaming('application/octet-stream'));
    }
}
