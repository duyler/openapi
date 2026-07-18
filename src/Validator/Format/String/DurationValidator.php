<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;
use function str_starts_with;

final readonly class DurationValidator extends AbstractStringFormatValidator
{
    private const string DURATION_PATTERN = '/^P(?:(?:(?<years>\d+)Y)?(?:(?<months>\d+)M)?(?:(?<days>\d+)D)?(?:T(?:(?<hours>\d+)H)?(?:(?<minutes>\d+)M)?(?:(?<seconds>\d+)S)?)?|(?<weeks>\d+)W)$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'duration';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (false === str_starts_with($data, 'P')) {
            throw new InvalidFormatException('duration', $data, 'Duration must start with P');
        }

        if (1 !== preg_match(self::DURATION_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('duration', $data, 'Invalid duration format');
        }

        $hasWeeksComponent = '' !== ($matches['weeks'] ?? '');
        $hasDateComponent = '' !== ($matches['years'] ?? '')
            || '' !== ($matches['months'] ?? '')
            || '' !== ($matches['days'] ?? '');
        $hasTimeComponent = '' !== ($matches['hours'] ?? '')
            || '' !== ($matches['minutes'] ?? '')
            || '' !== ($matches['seconds'] ?? '');

        if (false === $hasWeeksComponent && false === $hasDateComponent && false === $hasTimeComponent) {
            throw new InvalidFormatException('duration', $data, 'Duration must have at least one component');
        }
    }
}
