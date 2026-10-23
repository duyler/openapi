<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

use function array_key_exists;
use function assert;
use function is_string;
use function sprintf;
use function strlen;

abstract readonly class AbstractBaseEncodingSyntaxCheck implements EncodingSyntaxCheck
{
    private const string SHAPE_PATTERN = '/^(?<data>%s*)(?<padding>=*)$/';

    public function __construct(
        protected readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    public function isValid(string $data): bool
    {
        $matches = [];

        try {
            $matched = $this->pregExecutor->match(sprintf(self::SHAPE_PATTERN, $this->alphabet()), $data, $matches);
        } catch (PregRuntimeException) {
            return false;
        }

        if (1 !== $matched) {
            return false;
        }

        $dataPart = $matches['data'] ?? '';
        assert(is_string($dataPart));
        $paddingPart = $matches['padding'] ?? '';
        assert(is_string($paddingPart));

        $dataLength = strlen($dataPart);
        $paddingLength = strlen($paddingPart);

        if (0 === $paddingLength) {
            return $this->isUnpaddedLengthValid($dataLength);
        }

        return 0 === ($dataLength + $paddingLength) % $this->quantumSize()
            && ($this->paddingByRemainder()[$dataLength % $this->quantumSize()] ?? null) === $paddingLength;
    }

    abstract protected function alphabet(): string;

    abstract protected function quantumSize(): int;

    /**
     * @return array<int, int>
     */
    abstract protected function paddingByRemainder(): array;

    private function isUnpaddedLengthValid(int $dataLength): bool
    {
        return 0 === $dataLength % $this->quantumSize()
            || array_key_exists($dataLength % $this->quantumSize(), $this->paddingByRemainder());
    }
}
