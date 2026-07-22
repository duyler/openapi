<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Validator\TypeFormatter;

use function array_key_exists;
use function array_values;
use function array_map;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function version_compare;

/**
 * Parses an OpenAPI 3.2 / JSON Schema 2020-12 wire-form array into a
 * {@see Schema} instance.
 *
 * This is the inverse of {@see SchemaToArrayConverter::toWireArray()}.
 * Centralising the parser here removes the field enumeration from
 * {@see SchemaBuilder::buildSchema()} so adding a new field requires editing
 * only {@see SchemaFieldMetadata} plus the constructor call below.
 *
 * The parser is version-aware (OpenAPI 3.0 / 3.1 / 3.2) for the
 * `exclusiveMinimum` / `exclusiveMaximum` / `type` migrations and routes
 * deprecation warnings through {@see DeprecationLogger}.
 */
final readonly class SchemaFromArrayConverter
{
    private const string DEPRECATION_VERSION = '3.2.0';

    public function __construct(
        private string $documentVersion = '',
        private DeprecationLogger $deprecationLogger = new DeprecationLogger(),
    ) {}

    public function fromArray(bool|array $data): Schema
    {
        if (is_bool($data)) {
            if ($data) {
                return new Schema();
            }

            return new Schema(not: new Schema());
        }

        $this->checkSchemaDeprecations($data);

        $multipleOf = TypeHelper::asFloatOrNull($data['multipleOf'] ?? null);

        if (null !== $multipleOf && $multipleOf <= 0.0) {
            throw new InvalidSchemaException(sprintf(
                'multipleOf MUST be strictly greater than 0, got %g',
                $multipleOf,
            ));
        }

        $hasRef = array_key_exists('$ref', $data);

        return new Schema(
            ref: TypeHelper::asStringOrNull($data['$ref'] ?? null),
            refSummary: $hasRef ? TypeHelper::asStringOrNull($data['summary'] ?? null) : null,
            refDescription: $hasRef ? TypeHelper::asStringOrNull($data['description'] ?? null) : null,
            format: TypeHelper::asStringOrNull($data['format'] ?? null),
            title: TypeHelper::asStringOrNull($data['title'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            default: $data['default'] ?? null,
            hasDefault: array_key_exists('default', $data),
            deprecated: (bool) ($data['deprecated'] ?? false),
            readOnly: (bool) ($data['readOnly'] ?? false),
            writeOnly: (bool) ($data['writeOnly'] ?? false),
            type: $this->resolveType($data),
            nullable: (bool) ($data['nullable'] ?? false),
            const: $data['const'] ?? null,
            hasConst: array_key_exists('const', $data),
            multipleOf: $multipleOf,
            maximum: TypeHelper::asFloatOrNull($data['maximum'] ?? null),
            exclusiveMaximum: $this->resolveExclusiveMaximum($data),
            minimum: TypeHelper::asFloatOrNull($data['minimum'] ?? null),
            exclusiveMinimum: $this->resolveExclusiveMinimum($data),
            maxLength: TypeHelper::asIntOrNull($data['maxLength'] ?? null),
            minLength: TypeHelper::asIntOrNull($data['minLength'] ?? null),
            pattern: TypeHelper::asStringOrNull($data['pattern'] ?? null),
            maxItems: TypeHelper::asIntOrNull($data['maxItems'] ?? null),
            minItems: TypeHelper::asIntOrNull($data['minItems'] ?? null),
            uniqueItems: TypeHelper::asBoolOrNull($data['uniqueItems'] ?? null),
            maxProperties: TypeHelper::asIntOrNull($data['maxProperties'] ?? null),
            minProperties: TypeHelper::asIntOrNull($data['minProperties'] ?? null),
            required: TypeHelper::asStringListOrNull($data['required'] ?? null),
            allOf: $this->buildSchemaList($data, 'allOf'),
            anyOf: $this->buildSchemaList($data, 'anyOf'),
            oneOf: $this->buildSchemaList($data, 'oneOf'),
            not: $this->schemaOrBoolOrNull($data, 'not'),
            discriminator: isset($data['discriminator']) ? $this->buildDiscriminator(TypeHelper::asArray($data['discriminator'])) : null,
            properties: isset($data['properties']) && is_array($data['properties'])
                ? $this->buildProperties(TypeHelper::asArray($data['properties']))
                : null,
            additionalProperties: $this->buildOptionalSchema($data, 'additionalProperties'),
            unevaluatedProperties: $this->buildOptionalSchema($data, 'unevaluatedProperties'),
            items: $this->schemaOrBoolOrNull($data, 'items'),
            prefixItems: $this->buildSchemaList($data, 'prefixItems'),
            contains: $this->schemaOrBoolOrNull($data, 'contains'),
            minContains: TypeHelper::asIntOrNull($data['minContains'] ?? null),
            maxContains: TypeHelper::asIntOrNull($data['maxContains'] ?? null),
            patternProperties: isset($data['patternProperties']) && is_array($data['patternProperties'])
                ? $this->buildProperties(TypeHelper::asArray($data['patternProperties']))
                : null,
            propertyNames: $this->schemaOrBoolOrNull($data, 'propertyNames'),
            dependentSchemas: isset($data['dependentSchemas']) && is_array($data['dependentSchemas'])
                ? $this->buildProperties(TypeHelper::asArray($data['dependentSchemas']))
                : null,
            if: $this->schemaOrBoolOrNull($data, 'if'),
            then: $this->schemaOrBoolOrNull($data, 'then'),
            else: $this->schemaOrBoolOrNull($data, 'else'),
            unevaluatedItems: $this->schemaOrBoolOrNull($data, 'unevaluatedItems'),
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            enum: TypeHelper::asEnumListOrNull($data['enum'] ?? null),
            contentEncoding: TypeHelper::asStringOrNull($data['contentEncoding'] ?? null),
            contentMediaType: TypeHelper::asStringOrNull($data['contentMediaType'] ?? null),
            contentSchema: $this->buildOptionalSchema($data, 'contentSchema'),
            jsonSchemaDialect: TypeHelper::asStringOrNull($data['$schema'] ?? null),
            xml: isset($data['xml']) && is_array($data['xml'])
                ? $this->buildXml(TypeHelper::asArray($data['xml']))
                : null,
        );
    }

    /**
     * Returns the sub-schema value for a schema-typed keyword, passing
     * boolean values through directly per JSON Schema 2020-12 §4.3.2
     * (Boolean JSON Schemas). Returns null when the keyword is absent.
     *
     * @param array<array-key, mixed> $data
     */
    private function schemaOrBoolOrNull(array $data, string $key): Schema|bool|null
    {
        if (false === isset($data[$key])) {
            return null;
        }

        /** @var mixed $value */
        $value = $data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->fromArray($value);
        }

        throw new InvalidSchemaException(sprintf(
            'Expected array or boolean for schema keyword "%s", got %s',
            $key,
            TypeFormatter::format($value),
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function checkSchemaDeprecations(array $data): void
    {
        if (false === $this->shouldWarnDeprecation()) {
            return;
        }

        if (isset($data['example'])) {
            $this->deprecationLogger->warn(
                'example',
                'Schema Object',
                self::DEPRECATION_VERSION,
                'examples in MediaType Object',
            );
        }

        if (isset($data['nullable']) && $data['nullable']) {
            $this->deprecationLogger->warn(
                'nullable',
                'Schema Object',
                self::DEPRECATION_VERSION,
                'type array with "null" (e.g., type: ["string", "null"])',
            );
        }

        if (isset($data['exclusiveMinimum']) && is_bool($data['exclusiveMinimum'])) {
            $this->deprecationLogger->warn(
                'exclusiveMinimum (bool)',
                'Schema Object',
                '3.1.0',
                'exclusiveMinimum as number',
            );
        }

        if (isset($data['exclusiveMaximum']) && is_bool($data['exclusiveMaximum'])) {
            $this->deprecationLogger->warn(
                'exclusiveMaximum (bool)',
                'Schema Object',
                '3.1.0',
                'exclusiveMaximum as number',
            );
        }
    }

    private function shouldWarnDeprecation(): bool
    {
        return version_compare($this->documentVersion, self::DEPRECATION_VERSION, '>=');
    }

    private function isVersion30(): bool
    {
        return version_compare($this->documentVersion, '3.1.0', '<');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return string|list<string>|null
     */
    private function resolveType(array $data): string|array|null
    {
        $type = TypeHelper::asTypeOrNull($data['type'] ?? null);

        if (false === $this->isVersion30() && true === ($data['nullable'] ?? false)) {
            $baseType = is_array($type) ? array_values($type) : (is_string($type) ? [$type] : []);

            if ([] === $baseType) {
                /** @var string|list<string>|null $type */
                return $type;
            }

            if (false === in_array('null', $baseType, true)) {
                $baseType[] = 'null';
            }

            /** @var list<string> $baseType */
            return $baseType;
        }

        /** @var string|list<string>|null $type */
        return $type;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function resolveExclusiveMinimum(array $data): ?float
    {
        /** @var mixed $exclusiveMinimum */
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

    /**
     * @param array<array-key, mixed> $data
     */
    private function resolveExclusiveMaximum(array $data): ?float
    {
        /** @var mixed $exclusiveMaximum */
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

    /**
     * @param array<array-key, mixed> $data
     *
     * @return ?list<Schema>
     */
    private function buildSchemaList(array $data, string $key): ?array
    {
        if (false === isset($data[$key])) {
            return null;
        }

        $items = TypeHelper::asArray($data[$key]);

        return array_values(array_map(
            function (mixed $schemaData): Schema {
                if (is_array($schemaData) || is_bool($schemaData)) {
                    return $this->fromArray($schemaData);
                }

                throw new InvalidSchemaException(
                    sprintf('Expected array or boolean for schema, got %s', TypeFormatter::format($schemaData)),
                );
            },
            $items,
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function buildOptionalSchema(array $data, string $key): Schema|bool|null
    {
        if (false === isset($data[$key])) {
            return null;
        }

        if (is_array($data[$key])) {
            return $this->fromArray(TypeHelper::asArray($data[$key]));
        }

        return (bool) $data[$key];
    }

    /**
     * @return array<string, Schema>
     */
    private function buildProperties(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $properties = [];

        foreach ($data as $name => $schema) {
            $properties[$name] = $this->fromArray($schema);
        }

        return $properties;
    }

    private function buildDiscriminator(array $data): Discriminator
    {
        return new Discriminator(
            propertyName: TypeHelper::asStringOrNull($data['propertyName'] ?? null),
            mapping: TypeHelper::asStringMapOrNull($data['mapping'] ?? null),
            defaultMapping: TypeHelper::asStringOrNull($data['defaultMapping'] ?? null),
        );
    }

    private function buildXml(array $data): Xml
    {
        if ($this->shouldWarnDeprecation()) {
            if (isset($data['attribute'])) {
                $this->deprecationLogger->warn(
                    'attribute',
                    'XML Object',
                    self::DEPRECATION_VERSION,
                    'nodeType: "attribute"',
                );
            }

            if (isset($data['wrapped'])) {
                $this->deprecationLogger->warn(
                    'wrapped',
                    'XML Object',
                    self::DEPRECATION_VERSION,
                );
            }
        }

        $xml = new Xml(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            namespace: TypeHelper::asStringOrNull($data['namespace'] ?? null),
            prefix: TypeHelper::asStringOrNull($data['prefix'] ?? null),
            attribute: TypeHelper::asBoolOrNull($data['attribute'] ?? null),
            wrapped: TypeHelper::asBoolOrNull($data['wrapped'] ?? null),
            nodeType: TypeHelper::asStringOrNull($data['nodeType'] ?? null),
        );

        if (null !== $xml->nodeType && !Xml::isValidNodeType($xml->nodeType)) {
            throw new InvalidSchemaException(
                sprintf(
                    'Invalid XML nodeType "%s". Must be one of: %s',
                    $xml->nodeType,
                    implode(', ', Xml::VALID_NODE_TYPES),
                ),
            );
        }

        return $xml;
    }
}
