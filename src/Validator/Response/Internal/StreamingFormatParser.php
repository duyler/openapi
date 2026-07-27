<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use Psr\Http\Message\StreamInterface;

/** @internal */
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
