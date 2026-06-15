<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;

final readonly class UuidValidator extends AbstractStringFormatValidator
{
    /**
     * RFC 4122 §4.1.7 nil UUID: the special UUID with all 128 bits set to
     * zero. It has version nibble 0 and variant nibble 0, so the standard
     * UUID_PATTERN (which requires version 1-5 and variant 8/9/a/b) does
     * not match it. It is validated explicitly before applying the pattern.
     */
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';

    private const string UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'uuid';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if ($data === self::NIL_UUID) {
            return;
        }

        if (1 !== preg_match(self::UUID_PATTERN, $data)) {
            throw new InvalidFormatException('uuid', $data, 'Invalid UUID format');
        }
    }
}
