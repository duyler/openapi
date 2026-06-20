<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function count;

/**
 * Matches a concrete request media type against the media types declared
 * in an OpenAPI operation body, honouring RFC 7231 §3.1.1.1 wildcard
 * patterns. The most specific declaration wins, from exact match through
 * subtype wildcard to the universal wildcard.
 */
final readonly class MediaTypeMatcher
{
    /**
     * @param list<string> $specMediaTypes lower-cased keys declared in the spec
     *
     * @return string|null the matching spec key, or null when no declaration matches
     */
    public static function findMatch(string $requestMediaType, array $specMediaTypes): ?string
    {
        foreach ($specMediaTypes as $specType) {
            if ($requestMediaType === $specType) {
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
}
