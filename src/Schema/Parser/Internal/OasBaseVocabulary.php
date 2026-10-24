<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use function in_array;

/** @internal */
final class OasBaseVocabulary
{
    /** @var list<string> */
    public const array KEYWORDS = ['externalDocs'];

    public static function isKnown(string $keyword): bool
    {
        return in_array($keyword, self::KEYWORDS, true);
    }
}
