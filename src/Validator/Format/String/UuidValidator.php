<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class UuidValidator extends AbstractStringFormatValidator
{
    private const string UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/';

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    #[Override]
    protected function getFormatName(): string
    {
        return 'uuid';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (1 !== $this->pregExecutor->match(self::UUID_PATTERN, $data)) {
            throw new InvalidFormatException('uuid', $data, 'Invalid UUID format');
        }
    }
}
