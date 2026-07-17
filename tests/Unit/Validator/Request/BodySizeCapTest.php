<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Test\Support\StreamStubHelper;
use Duyler\OpenApi\Validator\BodyReader;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

use function str_repeat;

#[CoversClass(BodyReader::class)]
final class BodySizeCapTest extends TestCase
{
    use StreamStubHelper;

    private const int JSON_CAP = 10_485_760;

    private const int MULTIPART_CAP = 52_428_800;

    #[Test]
    public function request_body_under_json_cap_accepted(): void
    {
        $body = '{"valid":true}';

        $stream = $this->createInMemoryStream($body);

        $result = BodyReader::readSafely($stream, self::JSON_CAP);

        self::assertSame($body, $result);
    }

    #[Test]
    public function request_body_over_json_cap_throws_body_too_large(): void
    {
        $oversizeBytes = 11_010_048;

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn($oversizeBytes);

        $this->expectException(BodyTooLargeException::class);
        $this->expectExceptionMessage((string) $oversizeBytes);
        $this->expectExceptionMessage((string) self::JSON_CAP);

        BodyReader::readSafely($stream, self::JSON_CAP);
    }

    #[Test]
    public function request_body_under_multipart_cap_accepted(): void
    {
        $body = "--boundary\r\nContent-Disposition: form-data; name=\"field\"\r\n\r\nvalue\r\n--boundary--\r\n";

        $stream = $this->createInMemoryStream($body);

        $result = BodyReader::readSafely($stream, self::MULTIPART_CAP);

        self::assertSame($body, $result);
    }

    #[Test]
    public function request_body_over_multipart_cap_throws(): void
    {
        $oversizeBytes = 60_000_000;

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn($oversizeBytes);

        $this->expectException(BodyTooLargeException::class);
        $this->expectExceptionMessage((string) $oversizeBytes);
        $this->expectExceptionMessage((string) self::MULTIPART_CAP);

        BodyReader::readSafely($stream, self::MULTIPART_CAP);
    }

    #[Test]
    public function chunked_transfer_encoding_unknown_size_still_enforced(): void
    {
        $cap = 100;
        $chunk = str_repeat('a', 60);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(null);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willReturn($chunk, $chunk, $chunk);

        $caught = null;

        try {
            BodyReader::readSafely($stream, $cap);
        } catch (BodyTooLargeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(BodyTooLargeException::class, $caught);
        self::assertSame($cap, $caught->maxBytes);
        self::assertGreaterThan($cap, $caught->actualBytes);
    }

    #[Test]
    public function chunked_transfer_encoding_unknown_size_under_cap_accepted(): void
    {
        $cap = 200;
        $firstChunk = str_repeat('a', 60);
        $secondChunk = str_repeat('b', 60);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(null);
        $stream->method('eof')->willReturn(false, false, true);
        $stream->method('read')->willReturn($firstChunk, $secondChunk);

        $result = BodyReader::readSafely($stream, $cap);

        self::assertSame($firstChunk . $secondChunk, $result);
    }

    #[Test]
    public function empty_body_returns_empty_string(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getSize')->willReturn(0);
        $stream->method('eof')->willReturn(true);

        $result = BodyReader::readSafely($stream, self::JSON_CAP);

        self::assertSame('', $result);
    }

    private function createInMemoryStream(string $body): StreamInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $this->configureReadableStream($stream, $body);

        return $stream;
    }
}
