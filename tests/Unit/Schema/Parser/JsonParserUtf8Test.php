<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class JsonParserUtf8Test extends TestCase
{
    private JsonParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonParser();
    }

    #[Test]
    public function parse_invalid_utf8_json_spec_throws_invalid_utf8_exception(): void
    {
        $content = "{\"openapi\":\"3.0.3\",\"info\":{\"title\":\"\xC0\x80\",\"version\":\"1.0.0\"},\"paths\":{}}";

        $this->expectException(InvalidUtf8Exception::class);

        $this->parser->parse($content);
    }

    #[Test]
    public function parse_valid_utf8_json_spec_accepted(): void
    {
        $content = '{"openapi":"3.0.3","info":{"title":"héllo 中文 🎉","version":"1.0.0"},"paths":{}}';

        $document = $this->parser->parse($content);

        $this->assertSame('héllo 中文 🎉', $document->info->title);
    }

    #[Test]
    public function parse_iso_latin_1_byte_sequence_rejected(): void
    {
        $content = "{\"openapi\":\"3.0.3\",\"info\":{\"title\":\"\xE9\",\"version\":\"1.0.0\"},\"paths\":{}}";

        $this->expectException(InvalidUtf8Exception::class);

        $this->parser->parse($content);
    }
}
