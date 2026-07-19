<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class TimeValidator extends AbstractStringFormatValidator
{
    private const string TIME_PATTERN = '/^'
        . '(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d):(?<second>[0-5]\d|60)'
        . '(?:\.\d+)?'
        . '(?:[Zz]|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))?'
        . '$/';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'time';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $matches = [];

        if (1 !== $this->pregExecutor->match(self::TIME_PATTERN, $data, $matches)) {
            throw new InvalidFormatException('time', $data, 'Invalid time format');
        }

        if (
            60 === (int) $matches['second']
            && (23 !== (int) $matches['hour'] || 59 !== (int) $matches['minute'])
        ) {
            throw new InvalidFormatException('time', $data, 'Invalid time value');
        }
    }
}
