<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

/** @internal */
final readonly class SentinelValue
{
    public function __construct(public string|int|float|bool|array|null $value) {}
}
