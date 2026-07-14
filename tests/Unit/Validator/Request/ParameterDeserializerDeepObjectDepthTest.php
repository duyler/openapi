<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function microtime;
use function str_repeat;

/** @internal */
final class ParameterDeserializerDeepObjectDepthTest extends TestCase
{
    private ParameterDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new ParameterDeserializer();
    }

    #[Test]
    public function json_string_nested_at_untrusted_depth_minus_one_decoded_to_array(): void
    {
        $withinDepth = JsonDepthLimit::Untrusted->value - 1;
        $payload = str_repeat('[', $withinDepth) . '1' . str_repeat(']', $withinDepth);

        $result = $this->deserializer->deserialize($payload, $this->deepObjectParameter());

        $this->assertIsArray($result);
    }

    #[Test]
    public function json_string_nested_at_untrusted_depth_degrades_to_array_value(): void
    {
        $atDepth = JsonDepthLimit::Untrusted->value;
        $payload = str_repeat('[', $atDepth) . '1' . str_repeat(']', $atDepth);

        $result = $this->deserializer->deserialize($payload, $this->deepObjectParameter());

        $this->assertSame(
            [$payload],
            $result,
            'Excessive depth triggers JsonException and falls through to existing (array) $value fallback',
        );
    }

    #[Test]
    public function huge_depth_json_string_degrades_to_array_value_fast(): void
    {
        $hugeDepth = 10_000;
        $payload = str_repeat('[', $hugeDepth) . '1' . str_repeat(']', $hugeDepth);

        $start = microtime(true);

        $result = $this->deserializer->deserialize($payload, $this->deepObjectParameter());

        $elapsed = microtime(true) - $start;

        $this->assertSame([$payload], $result);
        $this->assertLessThan(
            0.05,
            $elapsed,
            'Deep-nested deepObject payload must fall through to (array) $value in under 50ms',
        );
    }

    private function deepObjectParameter(): Parameter
    {
        return new Parameter(
            name: 'props',
            in: 'query',
            style: 'deepObject',
            explode: false,
        );
    }
}
