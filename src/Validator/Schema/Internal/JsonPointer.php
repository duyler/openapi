<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use function str_replace;

/** @internal */
final class JsonPointer
{
    public static function escapeToken(string|int $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], (string) $token);
    }
}
