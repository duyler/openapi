<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;

/** @internal */
final readonly class OrderedMultipartPlan
{
    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<int, Encoding>|null $prefixEncoding
     * @param list<mixed> $instance
     */
    public function __construct(
        public readonly array $parts,
        public readonly ?array $prefixEncoding,
        public readonly ?Encoding $itemEncoding,
        public readonly array $instance,
    ) {}

    public function applyTo(MultipartEncodingApplier $applier): void
    {
        $applier->applyToParts(
            parts: $this->parts,
            prefixEncoding: $this->prefixEncoding,
            itemEncoding: $this->itemEncoding,
        );
    }
}
