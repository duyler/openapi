<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function microtime;
use function str_repeat;

/** @internal */
final class JsonBodyParserDepthTest extends TestCase
{
    private JsonBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonBodyParser();
    }

    #[Test]
    public function payload_nested_at_untrusted_depth_minus_one_accepted(): void
    {
        $withinDepth = JsonDepthLimit::Untrusted->value - 1;
        $body = str_repeat('[', $withinDepth) . '1' . str_repeat(']', $withinDepth);

        $result = $this->parser->parse($body);

        $this->assertNotNull($result);
    }

    #[Test]
    public function payload_nested_at_untrusted_depth_rejected_with_json_exception(): void
    {
        $atDepth = JsonDepthLimit::Untrusted->value;
        $body = str_repeat('[', $atDepth) . '1' . str_repeat(']', $atDepth);

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Maximum stack depth exceeded');

        $this->parser->parse($body);
    }

    #[Test]
    public function huge_depth_payload_rejected_fast(): void
    {
        $hugeDepth = 10_000;
        $body = str_repeat('[', $hugeDepth) . '1' . str_repeat(']', $hugeDepth);

        $start = microtime(true);

        try {
            $this->parser->parse($body);
            self::fail('Expected JsonException for huge depth payload');
        } catch (JsonException $e) {
            $elapsed = microtime(true) - $start;

            $this->assertStringContainsString('Maximum stack depth exceeded', $e->getMessage());
            $this->assertLessThan(
                0.05,
                $elapsed,
                'Deep-nested payload must be rejected in under 50ms to close the DoS vector',
            );
        }
    }
}
