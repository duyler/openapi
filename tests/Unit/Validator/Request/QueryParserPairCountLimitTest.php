<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;
use function sprintf;

/** @internal */
#[CoversClass(QueryParser::class)]
final class QueryParserPairCountLimitTest extends TestCase
{
    private QueryParser $parser;

    #[Override]
    protected function setUp(): void
    {
        $this->parser = new QueryParser();
    }

    #[Test]
    public function accepts_query_at_max_pairs(): void
    {
        $pairs = [];
        for ($i = 0; $i < 1000; ++$i) {
            $pairs[] = sprintf('k%d=v%d', $i, $i);
        }
        $queryString = implode('&', $pairs);

        $result = $this->parser->parse($queryString);

        self::assertCount(1000, $result);
    }

    #[Test]
    public function rejects_query_exceeding_max_pairs(): void
    {
        $pairs = [];
        for ($i = 0; $i < 1001; ++$i) {
            $pairs[] = sprintf('k%d=v%d', $i, $i);
        }
        $queryString = implode('&', $pairs);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid parameter "query": Maximum query string pairs of 1000 exceeded');

        $this->parser->parse($queryString);
    }

    #[Test]
    public function empty_query_still_returns_empty_array(): void
    {
        $result = $this->parser->parse('');

        self::assertSame([], $result);
    }

    #[Test]
    public function trailing_ampersand_at_max_pairs_does_not_trigger_limit(): void
    {
        $pairs = [];
        for ($i = 0; $i < 999; ++$i) {
            $pairs[] = sprintf('k%d=v%d', $i, $i);
        }
        $queryString = implode('&', $pairs) . '&';

        $result = $this->parser->parse($queryString);

        self::assertCount(999, $result);
    }
}
