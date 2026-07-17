<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Validator\Exception\MalformedStreamRecordException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function json_decode;
use function json_encode;
use function str_repeat;

#[CoversClass(StreamingContentParser::class)]
final class StreamingDepthTest extends TestCase
{
    #[Test]
    public function streaming_uses_untrusted_depth_128(): void
    {
        $depthExceeding = JsonDepthLimit::Untrusted->value + 2;
        $nestedPayload = $this->buildNestedJsonObject($depthExceeding);

        $parser = new StreamingContentParser(new NullLogger(), strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseJsonLines($nestedPayload);
    }

    #[Test]
    public function depth_just_under_untrusted_limit_accepted(): void
    {
        $safeDepth = JsonDepthLimit::Untrusted->value - 1;
        $nestedPayload = $this->buildNestedJsonObject($safeDepth);

        $parser = new StreamingContentParser();

        $result = $parser->parseJsonLines($nestedPayload);

        self::assertNotNull($result[0] ?? null);
    }

    #[Test]
    public function streaming_uses_untrusted_depth_for_json_seq(): void
    {
        $depthExceeding = JsonDepthLimit::Untrusted->value + 5;
        $nestedJson = $this->buildNestedJsonObject($depthExceeding);
        $body = "\x1E" . $nestedJson;

        $parser = new StreamingContentParser(new NullLogger(), strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseJsonSeq($body);
    }

    #[Test]
    public function streaming_uses_untrusted_depth_for_sse_data(): void
    {
        $depthExceeding = JsonDepthLimit::Untrusted->value + 1;
        $nestedJson = $this->buildNestedJsonObject($depthExceeding);
        $body = "event: test\ndata: " . $nestedJson . "\n\n";

        $parser = new StreamingContentParser(new NullLogger(), strictStreaming: true);

        $this->expectException(MalformedStreamRecordException::class);

        $parser->parseServerSentEvents($body);
    }

    private function buildNestedJsonObject(int $depth): string
    {
        $value = str_repeat('a', 3);

        for ($i = 0; $i < $depth; $i++) {
            $value = ['nested' => $value];
        }

        $encoded = (string) json_encode($value);
        $decoded = json_decode($encoded, true, $depth + 1);

        self::assertNotNull($decoded, 'Nested fixture must be valid JSON at depth ' . $depth);

        return $encoded;
    }
}
