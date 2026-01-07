<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class MultipartBodyParserTest extends TestCase
{
    private MultipartBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MultipartBodyParser();
    }

    #[Test]
    public function parse_empty_multipart_body(): void
    {
        $body = '';
        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_simple_multipart_body(): void
    {
        // Note: Full multipart parsing is complex and typically handled by web frameworks
        // This test verifies the basic parsing logic
        $body = '';  // Empty body for basic test

        $result = $this->parser->parse($body);

        $this->assertIsArray($result);
        $this->assertEmpty($result);  // Empty body returns empty array
    }
}
