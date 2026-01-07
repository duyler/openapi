<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Validator\Request\QueryParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class QueryParserTest extends TestCase
{
    private QueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QueryParser();
    }

    #[Test]
    public function parse_simple_query(): void
    {
        $result = $this->parser->parse('foo=bar&baz=qux');

        $this->assertSame(['foo' => 'bar', 'baz' => 'qux'], $result);
    }

    #[Test]
    public function parse_empty_query(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_array_values(): void
    {
        $result = $this->parser->parse('foo[]=1&foo[]=2');

        $this->assertSame(['foo' => ['1', '2']], $result);
    }

    #[Test]
    public function parse_object_values(): void
    {
        $result = $this->parser->parse('foo[a]=1&foo[b]=2');

        $this->assertSame(['foo' => ['a' => '1', 'b' => '2']], $result);
    }

    #[Test]
    public function handle_explode_true(): void
    {
        $result = $this->parser->handleExplode(['1', '2', '3'], true);

        $this->assertSame(['1', '2', '3'], $result);
    }

    #[Test]
    public function handle_explode_false(): void
    {
        $result = $this->parser->handleExplode(['1', '2', '3'], false);

        $this->assertSame('1,2,3', $result);
    }
}
