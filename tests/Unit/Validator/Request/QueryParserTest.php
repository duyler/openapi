<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
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

    #[Test]
    public function parse_query_string_json(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $result = $this->parser->parseQueryString('{"name":"John","age":30}', $parameter);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_query_string_json_url_encoded(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $result = $this->parser->parseQueryString('%7B%22name%22%3A%22John%22%2C%22age%22%3A30%7D', $parameter);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_query_string_plain_text(): void
    {
        $parameter = new Parameter(
            name: 'data',
            in: 'querystring',
            content: new Content([
                'text/plain' => new MediaType(
                    schema: new Schema(type: 'string'),
                ),
            ]),
        );

        $result = $this->parser->parseQueryString('raw text value', $parameter);

        $this->assertSame('raw text value', $result);
    }

    #[Test]
    public function parse_query_string_non_querystring_param(): void
    {
        $parameter = new Parameter(
            name: 'foo',
            in: 'query',
            schema: new Schema(type: 'string'),
        );

        $result = $this->parser->parseQueryString('anything', $parameter);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_query_string_without_content(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
        );

        $result = $this->parser->parseQueryString('{"name":"John"}', $parameter);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_query_string_invalid_json(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Malformed value');

        $this->parser->parseQueryString('not valid json', $parameter);
    }

    #[Test]
    public function parse_query_string_unsupported_media_type(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/xml' => new MediaType(
                    schema: new Schema(type: 'string'),
                ),
            ]),
        );

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->parser->parseQueryString('<root/>', $parameter);
    }

    #[Test]
    public function parse_query_string_json_array(): void
    {
        $parameter = new Parameter(
            name: 'ids',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'array', items: new Schema(type: 'integer')),
                ),
            ]),
        );

        $result = $this->parser->parseQueryString('[1,2,3,4,5]', $parameter);

        $this->assertSame([1, 2, 3, 4, 5], $result);
    }

    #[Test]
    public function parse_query_string_empty_string(): void
    {
        $parameter = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(type: 'object'),
                ),
            ]),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Malformed value');

        $this->parser->parseQueryString('', $parameter);
    }
}
