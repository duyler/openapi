<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use function preg_match;

/** @internal */
final readonly class PartDisposition
{
    private const string NAME_PATTERN = '/(?:^|;)\s*name="(?<name>[^"]*)"/i';

    /**
     * @param array<string, string> $headers
     */
    public static function name(array $headers): ?string
    {
        $disposition = $headers['content-disposition'] ?? null;

        if (null === $disposition) {
            return null;
        }

        if (1 === preg_match(self::NAME_PATTERN, $disposition, $matches)) {
            return $matches['name'];
        }

        return null;
    }
}
