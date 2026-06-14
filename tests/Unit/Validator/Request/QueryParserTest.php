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
    public function parse_duplicate_scalar_keys_collect_to_indexed_array(): void
    {
        $result = $this->parser->parse('tags=php&tags=go');

        $this->assertSame(['tags' => ['php', 'go']], $result);
    }

    #[Test]
    public function parse_duplicate_scalar_keys_three_values_collect_to_indexed_array(): void
    {
        $result = $this->parser->parse('tags=php&tags=go&tags=java');

        $this->assertSame(['tags' => ['php', 'go', 'java']], $result);
    }

    #[Test]
    public function parse_deep_object_flat_keys_preserve_associative_array(): void
    {
        $result = $this->parser->parse('filters[name]=John&filters[age]=30');

        $this->assertSame(['filters' => ['name' => 'John', 'age' => '30']], $result);
    }

    #[Test]
    public function parse_deep_object_two_level_nesting_preserves_structure(): void
    {
        $result = $this->parser->parse('filters[a][b]=value');

        $this->assertSame(['filters' => ['a' => ['b' => 'value']]], $result);
    }

    #[Test]
    public function parse_deep_object_five_level_nesting_preserves_full_structure(): void
    {
        $result = $this->parser->parse('filters[a][b][c][d][e]=v');

        $this->assertSame(
            ['filters' => ['a' => ['b' => ['c' => ['d' => ['e' => 'v']]]]]],
            $result,
        );
    }

    #[Test]
    public function parse_bracket_append_syntax_collects_to_indexed_array(): void
    {
        $result = $this->parser->parse('tags[]=php&tags[]=go');

        $this->assertSame(['tags' => ['php', 'go']], $result);
    }

    #[Test]
    public function parse_bracket_append_syntax_three_values_collects_to_indexed_array(): void
    {
        $result = $this->parser->parse('tags[]=php&tags[]=go&tags[]=java');

        $this->assertSame(['tags' => ['php', 'go', 'java']], $result);
    }

    #[Test]
    public function parse_comma_separated_value_stays_scalar(): void
    {
        $result = $this->parser->parse('tags=php,go');

        $this->assertSame(['tags' => 'php,go'], $result);
    }

    #[Test]
    public function parse_multiple_distinct_keys_preserve_each_value(): void
    {
        $result = $this->parser->parse('a=1&b=2&c=3');

        $this->assertSame(['a' => '1', 'b' => '2', 'c' => '3'], $result);
    }

    #[Test]
    public function parse_url_encoded_value_decodes_to_plain_string(): void
    {
        $result = $this->parser->parse('name=John%20Doe');

        $this->assertSame(['name' => 'John Doe'], $result);
    }

    #[Test]
    public function parse_plus_encoded_value_decodes_to_space(): void
    {
        $result = $this->parser->parse('name=John+Doe');

        $this->assertSame(['name' => 'John Doe'], $result);
    }

    #[Test]
    public function parse_key_without_value_yields_empty_string(): void
    {
        $result = $this->parser->parse('key');

        $this->assertSame(['key' => ''], $result);
    }

    #[Test]
    public function parse_key_with_empty_value_yields_empty_string(): void
    {
        $result = $this->parser->parse('key=');

        $this->assertSame(['key' => ''], $result);
    }

    #[Test]
    public function parse_duplicate_scalar_keys_mixed_with_distinct_keys(): void
    {
        $result = $this->parser->parse('tags=php&tags=go&page=1');

        $this->assertSame(['tags' => ['php', 'go'], 'page' => '1'], $result);
    }

    #[Test]
    public function parse_mixed_scalar_and_bracket_keys_delegate_to_parse_str(): void
    {
        $result = $this->parser->parse('tags=php&tags[]=go');

        $this->assertSame(['tags' => ['go']], $result);
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
