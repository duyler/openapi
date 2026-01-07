<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

final readonly class DurationValidator implements FormatValidatorInterface
{
    private const string DURATION_PATTERN = '/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/';

    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('duration', $data, 'Value must be a string');
        }

        if (!str_starts_with($data, 'P')) {
            throw new InvalidFormatException('duration', $data, 'Duration must start with P');
        }

        if (1 !== preg_match(self::DURATION_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('duration', $data, 'Invalid duration format');
        }

        $hasDateComponent = isset($matches[1]) || isset($matches[2]) || isset($matches[3]);
        $hasTimeComponent = isset($matches[4]) || isset($matches[5]) || isset($matches[6]);

        if ($hasTimeComponent && !str_contains($data, 'T')) {
            throw new InvalidFormatException('duration', $data, 'Time components must be preceded by T');
        }

        if (!$hasDateComponent && !$hasTimeComponent) {
            throw new InvalidFormatException('duration', $data, 'Duration must have at least one component');
        }
    }
}
