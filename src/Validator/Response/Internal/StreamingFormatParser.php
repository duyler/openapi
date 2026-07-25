<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Psr\Http\Message\StreamInterface;

/**
 * Polymorphic contract for the three streaming format parsers
 * (NDJSON, SSE, JSON Text Sequences). The StreamingContentParser
 * coordinator dispatches by Content-Type to a single implementation
 * of this interface.
 *
 * @internal
 */
interface StreamingFormatParser
{
    /**
     * @return list<array<int|string, mixed>|null>
     */
    public function parseString(string $body): array;

    /**
     * @return list<array<int|string, mixed>|null>
     */
    public function parseStream(StreamInterface $stream): array;
}
