<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Psr\Http\Message\StreamInterface;

use function strlen;

final readonly class BodyReader
{
    private const int CHUNK_SIZE = 8192;

    /**
     * Read a PSR-7 stream into a string while enforcing a hard byte cap.
     *
     * The stream is consumed in fixed-size chunks to avoid materialising an
     * attacker-controlled body in memory before the limit is checked. When the
     * underlying stream reports its size via getSize(), the cap is enforced
     * before any byte is read; otherwise the cap is enforced incrementally as
     * chunks arrive, so chunked transfer-encoding bodies are also protected.
     *
     * Seekable streams are rewound before reading so that the same request or
     * response object can be validated more than once (a common pattern in
     * tests and request-pipeline middleware). Non-seekable streams are read
     * from their current position.
     *
     * @throws BodyTooLargeException When the stream exceeds the configured cap.
     */
    public static function readSafely(StreamInterface $stream, int $maxBytes): string
    {
        $size = $stream->getSize();

        if (null !== $size && $size > $maxBytes) {
            throw new BodyTooLargeException($size, $maxBytes);
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';

        while (false === $stream->eof()) {
            $chunk = $stream->read(self::CHUNK_SIZE);

            if ('' === $chunk) {
                break;
            }

            $body .= $chunk;

            if (strlen($body) > $maxBytes) {
                throw new BodyTooLargeException(strlen($body), $maxBytes);
            }
        }

        return $body;
    }
}
