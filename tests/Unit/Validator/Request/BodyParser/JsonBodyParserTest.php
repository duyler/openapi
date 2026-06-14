<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\EmptyBodyException;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class JsonBodyParserTest extends TestCase
{
    private JsonBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonBodyParser();
    }

    #[Test]
    public function parse_json_body(): void
    {
        $body = '{"name":"John","age":30}';
        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_empty_string_throws_exception(): void
    {
        $body = '';

        $this->expectException(EmptyBodyException::class);
        $this->expectExceptionMessage('Request body cannot be empty');

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_whitespace_only_throws_exception(): void
    {
        $body = '   ';

        $this->expectException(EmptyBodyException::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function throw_error_for_invalid_json(): void
    {
        $body = '{invalid json}';

        $this->expectException(JsonException::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_json_body_with_utf8_bom(): void
    {
        $body = "\xEF\xBB\xBF" . '{"name":"John","age":30}';

        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_json_body_without_bom_returns_same_result(): void
    {
        $body = '{"name":"John","age":30}';

        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function parse_body_with_bom_and_invalid_json_throws_json_exception(): void
    {
        $body = "\xEF\xBB\xBF" . '{invalid json}';

        $this->expectException(JsonException::class);

        $this->parser->parse($body);
    }

    #[Test]
    public function parse_body_with_only_bom_throws_empty_body_exception(): void
    {
        $body = "\xEF\xBB\xBF";

        $this->expectException(EmptyBodyException::class);

        $this->parser->parse($body);
    }
}
