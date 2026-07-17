<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Psr\Http\Message\StreamInterface;

use function array_fill;
use function array_pad;
use function count;
use function str_split;
use function strlen;

/**
 * Stream stub helper for tests that exercise paths relying on the PSR-7
 * streaming read API (getSize / eof / read). PHPUnit createStub() returns
 * neutral defaults that defeat chunked reading; this helper wires a stub to
 * emit the configured body across 8192-byte chunks, matching BodyReader's
 * contract.
 */
trait StreamStubHelper
{
    private function configureReadableStream(StreamInterface $stream, string $body): void
    {
        $chunks = [] === $body ? [''] : str_split($body, 8192);
        $eofSequence = array_fill(0, count($chunks), false);
        $eofSequence[] = true;
        $readSequence = array_pad($chunks, count($chunks) + 1, '');

        $stream->method('getSize')->willReturn(strlen($body));
        $stream->method('eof')->willReturnOnConsecutiveCalls(...$eofSequence);
        $stream->method('read')->willReturnOnConsecutiveCalls(...$readSequence);
    }
}
