<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function in_array;

final readonly class TimeValidator extends AbstractStringFormatValidator
{
    private const string TIME_PATTERN = '/^'
        . '(?<hour>[01]\d|2[0-3]):(?<minute>[0-5]\d):(?<second>[0-5]\d|60)'
        . '(?:\.\d+)?'
        . '(?<offset>[Zz]|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))?'
        . '$/';

    /** @var list<string> */
    private const array UTC_OFFSETS = ['Z', 'z', '+00:00', '-00:00'];

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

        if (60 !== (int) $matches['second']) {
            return;
        }

        $isEndOfDay = 23 === (int) $matches['hour'] && 59 === (int) $matches['minute'];
        $isUtc = in_array($matches['offset'] ?? '', self::UTC_OFFSETS, true);

        if ($isEndOfDay && $isUtc) {
            return;
        }

        throw new InvalidFormatException(
            'time',
            $data,
            'Leap second requires UTC end-of-day (e.g., 23:59:60Z)',
        );
    }
}
