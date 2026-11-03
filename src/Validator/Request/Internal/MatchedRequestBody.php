<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\MediaType;

/** @internal */
final readonly class MatchedRequestBody
{
    public function __construct(
        public readonly string $requestMediaType,
        public readonly string $specMediaType,
        public readonly MediaType $content,
    ) {}
}
