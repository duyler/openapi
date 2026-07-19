<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class UuidValidator extends AbstractStringFormatValidator
{
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';

    private const string MAX_UUID = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    private const string UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';

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
        if (self::NIL_UUID === $data || self::MAX_UUID === $data) {
            return;
        }

        if (1 !== $this->pregExecutor->match(self::UUID_PATTERN, $data)) {
            throw new InvalidFormatException('uuid', $data, 'Invalid UUID format');
        }
    }
}
