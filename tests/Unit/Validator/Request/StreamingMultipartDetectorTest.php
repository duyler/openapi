<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Request\StreamingMultipartDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamingMultipartDetector::class)]
final class StreamingMultipartDetectorTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function provide_media_types(): array
    {
        return [
            'form data is multipart streaming candidate' => ['multipart/form-data', true],
            'mixed is multipart streaming candidate' => ['multipart/mixed', true],
            'byteranges is multipart streaming candidate' => ['multipart/byteranges', true],
            'mixed in upper case is a candidate' => ['Multipart/Mixed; boundary=b', true],
            'form data in upper case is a candidate' => ['Multipart/Form-Data', true],
            'related with parameters is candidate' => ['multipart/related; type=text/html; boundary=b', true],
            'json is not a candidate' => ['application/json', false],
            'ndjson is not a candidate' => ['application/x-ndjson', false],
            'event stream is not a candidate' => ['text/event-stream', false],
            'empty content type is not a candidate' => ['', false],
            'similar prefix is not a candidate' => ['multipartx/form-data', false],
        ];
    }

    #[Test]
    #[DataProvider('provide_media_types')]
    public function detection_follows_the_multipart_family(string $contentType, bool $expected): void
    {
        self::assertSame($expected, StreamingMultipartDetector::isStreaming($contentType));
    }
}
