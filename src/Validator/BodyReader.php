<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Psr\Http\Message\StreamInterface;

use function strlen;

final readonly class BodyReader
{
    private const int CHUNK_SIZE = 8192;

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

            $bodyLength = strlen($body);

            if ($bodyLength > $maxBytes) {
                throw new BodyTooLargeException($bodyLength, $maxBytes);
            }
        }

        return $body;
    }
}
