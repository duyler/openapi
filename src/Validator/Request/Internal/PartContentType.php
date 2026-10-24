<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\ExternalMediaType;

use function explode;
use function is_array;
use function str_ends_with;
use function strtolower;
use function trim;

/** @internal */
final readonly class PartContentType
{
    private const string BINARY = 'application/octet-stream';

    private const string TEXT = 'text/plain';

    private const string JSON = 'application/json';

    private const string JSON_SUFFIX = '+json';

    private const string NULL_TYPE = 'null';

    public static function effective(?string $headerValue, ?Encoding $encoding, ?Schema $schema): string
    {
        if (null !== $headerValue) {
            return self::normalize($headerValue);
        }

        if (null !== $encoding) {
            $declared = self::firstEntry(ExternalMediaType::primary($encoding->contentType, ''));

            if ('' !== $declared) {
                return self::normalize($declared);
            }
        }

        return self::defaultForSchema($schema);
    }

    public static function isJson(string $contentType): bool
    {
        return self::JSON === $contentType || str_ends_with($contentType, self::JSON_SUFFIX);
    }

    public static function defaultForSchema(?Schema $schema): string
    {
        $type = self::primaryType($schema);

        if (null === $type || null === $schema) {
            return self::BINARY;
        }

        if ('string' === $type) {
            return null === $schema->contentEncoding ? self::TEXT : self::BINARY;
        }

        return match ($type) {
            'object', 'array' => self::JSON,
            'number', 'integer', 'boolean' => self::TEXT,
            default => self::TEXT,
        };
    }

    public static function normalize(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    private static function firstEntry(string $contentType): string
    {
        return trim(explode(',', $contentType, 2)[0]);
    }

    private static function primaryType(?Schema $schema): ?string
    {
        $type = $schema?->type;

        if (null === $type) {
            return null;
        }

        if (false === is_array($type)) {
            return $type;
        }

        foreach ($type as $entry) {
            if (self::NULL_TYPE !== $entry) {
                return $entry;
            }
        }

        return null;
    }
}
