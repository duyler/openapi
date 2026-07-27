<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

use function array_key_exists;
use function is_bool;
use function sprintf;
use function version_compare;
use function in_array;
use function is_array;
use function is_string;

/** @internal */
final readonly class ScalarSchemaKeywordParser
{
    private const string DEPRECATION_VERSION_3_1 = '3.1.0';
    private const string DEPRECATION_VERSION_3_2 = '3.2.0';

    public function __construct(
        private string $documentVersion = '',
        private DeprecationLogger $deprecationLogger = new DeprecationLogger(),
    ) {}

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array{
     *     multipleOf: ?float,
     *     maximum: ?float,
     *     exclusiveMaximum: ?float,
     *     minimum: ?float,
     *     exclusiveMinimum: ?float,
     *     maxLength: ?int,
     *     minLength: ?int,
     *     pattern: ?string,
     *     format: ?string,
     *     title: ?string,
     *     description: ?string,
     *     default: mixed|null,
     *     hasDefault: bool,
     *     deprecated: bool,
     *     readOnly: bool,
     *     writeOnly: bool,
     *     type: string|array<int, string|null>|null,
     *     nullable: bool,
     *     const: mixed|null,
     *     hasConst: bool,
     *     contentEncoding: ?string,
     *     contentMediaType: ?string,
     *     jsonSchemaDialect: ?string,
     *     example: mixed|null,
     *     examples: array<string, mixed>|null,
     *     enum: list<mixed>|null,
     * }
     */
    public function build(array $data): array
    {
        $multipleOf = TypeHelper::asFloatOrNull($data['multipleOf'] ?? null);

        if (null !== $multipleOf && $multipleOf <= 0.0) {
            throw new InvalidSchemaException(sprintf(
                'multipleOf MUST be strictly greater than 0, got %g',
                $multipleOf,
            ));
        }

        $this->warnDeprecations($data);

        return [
            'multipleOf' => $multipleOf,
            'maximum' => TypeHelper::asFloatOrNull($data['maximum'] ?? null),
            'exclusiveMaximum' => $this->resolveExclusiveMaximum($data),
            'minimum' => TypeHelper::asFloatOrNull($data['minimum'] ?? null),
            'exclusiveMinimum' => $this->resolveExclusiveMinimum($data),
            'maxLength' => TypeHelper::asIntOrNull($data['maxLength'] ?? null),
            'minLength' => TypeHelper::asIntOrNull($data['minLength'] ?? null),
            'pattern' => TypeHelper::asStringOrNull($data['pattern'] ?? null),
            'format' => TypeHelper::asStringOrNull($data['format'] ?? null),
            'title' => TypeHelper::asStringOrNull($data['title'] ?? null),
            'description' => TypeHelper::asStringOrNull($data['description'] ?? null),
            'default' => $data['default'] ?? null,
            'hasDefault' => array_key_exists('default', $data),
            'deprecated' => (bool) ($data['deprecated'] ?? false),
            'readOnly' => (bool) ($data['readOnly'] ?? false),
            'writeOnly' => (bool) ($data['writeOnly'] ?? false),
            'type' => $this->resolveType($data),
            'nullable' => (bool) ($data['nullable'] ?? false),
            'const' => $data['const'] ?? null,
            'hasConst' => array_key_exists('const', $data),
            'contentEncoding' => TypeHelper::asStringOrNull($data['contentEncoding'] ?? null),
            'contentMediaType' => TypeHelper::asStringOrNull($data['contentMediaType'] ?? null),
            'jsonSchemaDialect' => TypeHelper::asStringOrNull($data['$schema'] ?? null),
            'example' => $data['example'] ?? null,
            'examples' => isset($data['examples']) && is_array($data['examples'])
                ? TypeHelper::asStringMixedMapOrNull($data['examples'])
                : null,
            'enum' => TypeHelper::asEnumListOrNull($data['enum'] ?? null),
        ];
    }

    /** @param array<array-key, mixed> $data */
    private function resolveType(array $data): string|array|null
    {
        $type = TypeHelper::asTypeOrNull($data['type'] ?? null);

        if (false === $this->isVersion30() && true === ($data['nullable'] ?? false)) {
            $baseType = match (true) {
                is_array($type) => array_values($type),
                is_string($type) => [$type],
                default => [],
            };

            if ([] === $baseType) {
                return $type;
            }

            if (false === in_array('null', $baseType, true)) {
                $baseType[] = 'null';
            }

            /** @var list<string> $baseType */
            return $baseType;
        }

        return $type;
    }

    /** @param array<array-key, mixed> $data */
    private function resolveExclusiveMinimum(array $data): ?float
    {
        $exclusiveMinimum = $data['exclusiveMinimum'] ?? null;

        if ($this->isVersion30()) {
            if (is_bool($exclusiveMinimum) && $exclusiveMinimum) {
                return TypeHelper::asFloatOrNull($data['minimum'] ?? null);
            }

            return null;
        }

        if (is_bool($exclusiveMinimum)) {
            return null;
        }

        return TypeHelper::asFloatOrNull($exclusiveMinimum);
    }

    /** @param array<array-key, mixed> $data */
    private function resolveExclusiveMaximum(array $data): ?float
    {
        $exclusiveMaximum = $data['exclusiveMaximum'] ?? null;

        if ($this->isVersion30()) {
            if (is_bool($exclusiveMaximum) && $exclusiveMaximum) {
                return TypeHelper::asFloatOrNull($data['maximum'] ?? null);
            }

            return null;
        }

        if (is_bool($exclusiveMaximum)) {
            return null;
        }

        return TypeHelper::asFloatOrNull($exclusiveMaximum);
    }

    /** @param array<array-key, mixed> $data */
    private function warnDeprecations(array $data): void
    {
        if (false === $this->shouldWarnDeprecation3_2()) {
            return;
        }

        if (isset($data['example'])) {
            $this->deprecationLogger->warn(
                'example',
                'Schema Object',
                self::DEPRECATION_VERSION_3_2,
                'examples in MediaType Object',
            );
        }

        if (isset($data['nullable']) && $data['nullable']) {
            $this->deprecationLogger->warn(
                'nullable',
                'Schema Object',
                self::DEPRECATION_VERSION_3_2,
                'type array with "null" (e.g., type: ["string", "null"])',
            );
        }

        if (isset($data['exclusiveMinimum']) && is_bool($data['exclusiveMinimum'])) {
            $this->deprecationLogger->warn(
                'exclusiveMinimum (bool)',
                'Schema Object',
                self::DEPRECATION_VERSION_3_1,
                'exclusiveMinimum as number',
            );
        }

        if (isset($data['exclusiveMaximum']) && is_bool($data['exclusiveMaximum'])) {
            $this->deprecationLogger->warn(
                'exclusiveMaximum (bool)',
                'Schema Object',
                self::DEPRECATION_VERSION_3_1,
                'exclusiveMaximum as number',
            );
        }
    }

    private function isVersion30(): bool
    {
        return version_compare($this->documentVersion, self::DEPRECATION_VERSION_3_1, '<');
    }

    private function shouldWarnDeprecation3_2(): bool
    {
        return version_compare($this->documentVersion, self::DEPRECATION_VERSION_3_2, '>=');
    }
}
