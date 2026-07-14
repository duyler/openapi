<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_repeat;

/** @internal */
final class MultipartBodyParserExplodeLimitTest extends TestCase
{
    private MultipartBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MultipartBodyParser();
    }

    #[Test]
    public function large_part_count_rejected(): void
    {
        $boundary = 'b123';
        $contentType = 'multipart/form-data; boundary=' . $boundary;

        // MAX_MULTIPART_PARTS = 1000 → 1001 boundary delimiters produces 1002 splits.
        $body = str_repeat('--' . $boundary . "\r\nX: 1\r\n\r\nv\r\n", 1001);

        $this->expectException(RuntimeException::class);

        $this->parser->parse($body, $contentType);
    }

    #[Test]
    public function normal_part_count_accepted(): void
    {
        $boundary = 'b123';
        $contentType = 'multipart/form-data; boundary=' . $boundary;

        // 5 parts — well under the 1000 cap.
        $body = '';
        for ($i = 0; $i < 5; ++$i) {
            $body .= '--' . $boundary . "\r\n"
                . "Content-Disposition: form-data; name=\"f{$i}\"\r\n"
                . "\r\n"
                . "v{$i}\r\n";
        }
        $body .= '--' . $boundary . '--';

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(5, $result);
    }
}
