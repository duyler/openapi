<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\SchemaFieldMetadata;

/** @internal */
final class KnownSchemaKeywords
{
    /** @var list<string> */
    private const array TRANSLATED_KEYWORDS = ['dependencies'];

    /** @var array<string, true>|null */
    private static ?array $registry = null;

    public static function isKnown(string $keyword): bool
    {
        $registry = self::$registry ??= self::buildRegistry();

        return isset($registry[$keyword]);
    }

    /** @return array<string, true> */
    private static function buildRegistry(): array
    {
        $registry = [];

        foreach (SchemaFieldMetadata::fields() as $field) {
            $registry[$field->openApiName] = true;
        }

        foreach (self::TRANSLATED_KEYWORDS as $keyword) {
            $registry[$keyword] = true;
        }

        return $registry;
    }
}
