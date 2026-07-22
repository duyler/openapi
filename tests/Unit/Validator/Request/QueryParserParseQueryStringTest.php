<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Request\QueryParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Content;

/** @internal */
final class QueryParserParseQueryStringTest extends TestCase
{
    private QueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QueryParser();
    }

    #[Test]
    public function parse_query_string_returns_null_for_non_querystring_in(): void
    {
        $param = new Parameter(name: 'id', in: 'query');

        $result = $this->parser->parseQueryString('id=1', $param);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_query_string_returns_null_for_null_content(): void
    {
        $param = new Parameter(name: 'filter', in: 'querystring');

        $result = $this->parser->parseQueryString('filter=test', $param);

        $this->assertNull($result);
    }

    #[Test]
    public function parse_query_string_parses_json(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content(
                mediaTypes: [
                    'application/json' => new MediaType(),
                ],
            ),
        );

        $result = $this->parser->parseQueryString('{"name":"test"}', $param);

        $this->assertSame(['name' => 'test'], $result);
    }

    #[Test]
    public function parse_query_string_parses_text(): void
    {
        $param = new Parameter(
            name: 'q',
            in: 'querystring',
            content: new Content(
                mediaTypes: [
                    'text/plain' => new MediaType(),
                ],
            ),
        );

        $result = $this->parser->parseQueryString('hello world', $param);

        $this->assertSame('hello world', $result);
    }

    #[Test]
    public function parse_query_string_throws_for_unsupported_media_type(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content(
                mediaTypes: [
                    'application/xml' => new MediaType(),
                ],
            ),
        );

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->parser->parseQueryString('<xml/>', $param);
    }

    #[Test]
    public function parse_query_string_throws_for_invalid_json(): void
    {
        $param = new Parameter(
            name: 'filter',
            in: 'querystring',
            content: new Content(
                mediaTypes: [
                    'application/json' => new MediaType(),
                ],
            ),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid parameter configuration');

        $this->parser->parseQueryString('{invalid}', $param);
    }
}
