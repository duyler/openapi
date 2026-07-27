<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use Duyler\OpenApi\Validator\Operation;

use function usort;

/** @internal */
final readonly class CandidatePrioritizer
{
    /**
     * @param array{0: Operation, 1: array<string, string>} $a
     * @param array{0: Operation, 1: array<string, string>} $b
     */
    public function compareByPlaceholderCount(array $a, array $b): int
    {
        return $a[0]->countPlaceholders() <=> $b[0]->countPlaceholders();
    }

    /**
     * @param array<int, array{0: Operation, 1: array<string, string>}> $candidates
     *
     * @return array{0: Operation, 1: array<string, string>}
     */
    public function prioritize(array $candidates): array
    {
        usort($candidates, $this->compareByPlaceholderCount(...));

        return $candidates[0];
    }
}
