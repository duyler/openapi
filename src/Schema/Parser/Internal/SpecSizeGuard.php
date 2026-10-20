<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;

use function is_array;
use function strlen;

/** @internal */
final readonly class SpecSizeGuard
{
    public const int DEFAULT_MAX_SPEC_BYTES = 1_048_576;

    public const int DEFAULT_MAX_SPEC_DEPTH = 100;

    public function __construct(
        private readonly int $maxBytes = self::DEFAULT_MAX_SPEC_BYTES,
        private readonly int $maxDepth = self::DEFAULT_MAX_SPEC_DEPTH,
    ) {}

    public function assertSize(string $content): void
    {
        $size = strlen($content);

        if ($size > $this->maxBytes) {
            throw SpecTooLargeException::forSize($this->maxBytes, $size);
        }
    }

    /** @param mixed $data */
    public function assertDepth(mixed $data): void
    {
        if (false === is_array($data)) {
            return;
        }

        $depth = $this->calculateDepth($data);

        if ($depth > $this->maxDepth) {
            throw SpecTooLargeException::forDepth($depth, $this->maxDepth);
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function calculateDepth(array $data, int $current = 0): int
    {
        $maxChildDepth = $current;

        foreach ($data as $value) {
            /** @var mixed $value */
            if (false === is_array($value)) {
                continue;
            }

            $childDepth = $this->calculateDepth($value, $current + 1);
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth;
    }
}
