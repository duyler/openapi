<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function count;
use function explode;
use function strtolower;
use function trim;

final readonly class MediaTypeMatcher
{
    /**
     * @param list<string> $specMediaTypes
     * @return string|null
     */
    public static function findMatch(string $requestMediaType, array $specMediaTypes): ?string
    {
        foreach ($specMediaTypes as $specType) {
            if ($requestMediaType === $specType) {
                return $specType;
            }
        }

        foreach ($specMediaTypes as $specType) {
            if ($requestMediaType === self::mediaTypeProper($specType)) {
                return $specType;
            }
        }

        $requestParts = explode('/', $requestMediaType, 2);

        if (2 === count($requestParts)) {
            $typeWildcard = $requestParts[0] . '/*';

            foreach ($specMediaTypes as $specType) {
                if ($typeWildcard === $specType) {
                    return $specType;
                }
            }
        }

        foreach ($specMediaTypes as $specType) {
            if ('*/*' === $specType) {
                return $specType;
            }
        }

        return null;
    }

    private static function mediaTypeProper(string $specType): string
    {
        return strtolower(trim(explode(';', $specType, 2)[0]));
    }
}
