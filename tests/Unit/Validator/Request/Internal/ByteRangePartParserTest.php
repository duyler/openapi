<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidContentRangeException;
use Duyler\OpenApi\Validator\Request\Internal\ByteRange;
use Duyler\OpenApi\Validator\Request\Internal\ByteRangePartParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ByteRangePartParserTest extends TestCase
{
    #[Test]
    public function content_range_header_is_parsed_strictly(): void
    {
        $range = ByteRangePartParser::parse('Content-Range', 'bytes 0-100/1000');

        self::assertNotNull($range);
        self::assertSame(0, $range->first);
        self::assertSame(100, $range->last);
        self::assertSame(1000, $range->complete);
    }

    #[Test]
    public function header_name_matching_is_case_insensitive(): void
    {
        $range = ByteRangePartParser::parse('CONTENT-RANGE', 'bytes */10');

        self::assertNotNull($range);
        self::assertSame(10, $range->complete);
    }

    #[Test]
    public function foreign_header_is_ignored(): void
    {
        self::assertNull(ByteRangePartParser::parse('Content-Type', 'bytes 0-100/1000'));
        self::assertNull(ByteRangePartParser::parse('Content-Length', '42'));
    }

    #[Test]
    public function malformed_content_range_value_propagates_the_domain_error(): void
    {
        $this->expectException(InvalidContentRangeException::class);

        ByteRangePartParser::parse('content-range', 'bytes 5-1/10');
    }

    #[Test]
    public function value_object_is_reused_from_the_parser(): void
    {
        self::assertInstanceOf(ByteRange::class, ByteRangePartParser::parse('content-range', 'bytes 0-1/2'));
    }
}
