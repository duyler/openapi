<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function strlen;
use function substr;

final readonly class DurationValidator extends AbstractStringFormatValidator
{
    private const string WEEKS_PATTERN = '/^\d+W/';

    private const string COMPONENT_PATTERN = '/^(\d+)([YMWDHS])/';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'duration';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (false === $this->isValidDuration($data)) {
            throw new InvalidFormatException('duration', $data, 'Invalid duration format');
        }
    }

    private function isValidDuration(string $data): bool
    {
        if ('P' !== ($data[0] ?? '')) {
            return false;
        }

        $rest = substr($data, 1);

        if ('' === $rest) {
            return false;
        }

        $length = strlen($rest);

        if (1 === $this->pregExecutor->match(self::WEEKS_PATTERN, $rest, $matches)) {
            return strlen((string) $matches[0]) === $length;
        }

        $position = $this->datePartEnd($rest, 0);

        if ($position > 0) {
            $position = $this->timePartEnd($rest, $position);

            return false !== $position && $position === $length;
        }

        $position = $this->timePartEnd($rest, 0);

        return false !== $position && $position === $length;
    }

    private function datePartEnd(string $value, int $start): int
    {
        $position = $this->componentEnd($value, $start, 'Y');

        if ($position > $start) {
            $monthEnd = $this->componentEnd($value, $position, 'M');

            if ($monthEnd > $position) {
                return $this->componentEnd($value, $monthEnd, 'D');
            }

            return $monthEnd;
        }

        $monthEnd = $this->componentEnd($value, $start, 'M');

        if ($monthEnd > $start) {
            return $this->componentEnd($value, $monthEnd, 'D');
        }

        return $this->componentEnd($value, $start, 'D');
    }

    private function timePartEnd(string $value, int $start): int|false
    {
        $length = strlen($value);

        if ($start >= $length || 'T' !== $value[$start]) {
            return $start;
        }

        $afterT = $start + 1;
        $position = $this->componentEnd($value, $afterT, 'H');

        if ($position > $afterT) {
            $minuteEnd = $this->componentEnd($value, $position, 'M');

            if ($minuteEnd > $position) {
                return $this->componentEnd($value, $minuteEnd, 'S');
            }

            return $minuteEnd;
        }

        $minuteEnd = $this->componentEnd($value, $afterT, 'M');

        if ($minuteEnd > $afterT) {
            return $this->componentEnd($value, $minuteEnd, 'S');
        }

        $secondEnd = $this->componentEnd($value, $afterT, 'S');

        if ($secondEnd > $afterT) {
            return $secondEnd;
        }

        return false;
    }

    private function componentEnd(string $value, int $start, string $unit): int
    {
        if (1 !== $this->pregExecutor->match(self::COMPONENT_PATTERN, substr($value, $start), $matches)) {
            return $start;
        }

        $number = (string) $matches[1];
        $foundUnit = (string) $matches[2];

        if ($foundUnit !== $unit) {
            return $start;
        }

        return $start + strlen($number) + 1;
    }
}
