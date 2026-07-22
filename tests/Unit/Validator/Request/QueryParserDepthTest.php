<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Request\QueryParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function microtime;
use function str_repeat;

/** @internal */
final class QueryParserDepthTest extends TestCase
{
    private QueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QueryParser();
    }

    #[Test]
    public function json_query_param_nested_at_untrusted_depth_minus_one_accepted(): void
    {
        $withinDepth = JsonDepthLimit::Untrusted->value - 1;
        $payload = str_repeat('[', $withinDepth) . '1' . str_repeat(']', $withinDepth);

        $result = $this->parser->parseQueryString($payload, $this->jsonParameter());

        $this->assertNotNull($result);
    }

    #[Test]
    public function json_query_param_nested_at_untrusted_depth_rejected(): void
    {
        $atDepth = JsonDepthLimit::Untrusted->value;
        $payload = str_repeat('[', $atDepth) . '1' . str_repeat(']', $atDepth);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid parameter configuration');

        $this->parser->parseQueryString($payload, $this->jsonParameter());
    }

    #[Test]
    public function huge_depth_json_query_param_rejected_fast(): void
    {
        $hugeDepth = 10_000;
        $payload = str_repeat('[', $hugeDepth) . '1' . str_repeat(']', $hugeDepth);

        $start = microtime(true);

        try {
            $this->parser->parseQueryString($payload, $this->jsonParameter());
            self::fail('Expected InvalidParameterException for huge depth payload');
        } catch (InvalidParameterException $e) {
            $elapsed = microtime(true) - $start;

            $this->assertStringContainsString('Invalid parameter configuration', $e->getMessage());
            $this->assertLessThan(
                0.05,
                $elapsed,
                'Deep-nested query param must be rejected in under 50ms to close the DoS vector',
            );
        }
    }

    private function jsonParameter(): Parameter
    {
        return new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content(
                mediaTypes: [
                    'application/json' => new MediaType(),
                ],
            ),
        );
    }
}
