<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

/**
 * Order matters: fields are emitted in the order listed here. The canonical
 * order matches the historical emission produced by the legacy
 * `Schema::jsonSerialize()` to keep the serialised byte output byte-stable.
 */
final readonly class SchemaFieldMetadata
{
    /**
     * @return list<FieldMetadata>
     */
    public static function fields(): array
    {
        return [
            new FieldMetadata('ref', '$ref', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('refSummary', 'summary', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('refDescription', 'description', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('title', 'title', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('description', 'description', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('default', 'default', FieldMetadata::CATEGORY_FLAT, true),
            new FieldMetadata('deprecated', 'deprecated', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('readOnly', 'readOnly', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('writeOnly', 'writeOnly', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('type', 'type', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('nullable', 'nullable', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('const', 'const', FieldMetadata::CATEGORY_FLAT, true),
            new FieldMetadata('multipleOf', 'multipleOf', FieldMetadata::CATEGORY_NUMERIC),
            new FieldMetadata('maximum', 'maximum', FieldMetadata::CATEGORY_NUMERIC),
            new FieldMetadata('exclusiveMaximum', 'exclusiveMaximum', FieldMetadata::CATEGORY_NUMERIC),
            new FieldMetadata('minimum', 'minimum', FieldMetadata::CATEGORY_NUMERIC),
            new FieldMetadata('exclusiveMinimum', 'exclusiveMinimum', FieldMetadata::CATEGORY_NUMERIC),
            new FieldMetadata('maxLength', 'maxLength', FieldMetadata::CATEGORY_STRING),
            new FieldMetadata('minLength', 'minLength', FieldMetadata::CATEGORY_STRING),
            new FieldMetadata('pattern', 'pattern', FieldMetadata::CATEGORY_STRING),
            new FieldMetadata('maxItems', 'maxItems', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('minItems', 'minItems', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('uniqueItems', 'uniqueItems', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('maxProperties', 'maxProperties', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('minProperties', 'minProperties', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('required', 'required', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('allOf', 'allOf', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('anyOf', 'anyOf', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('oneOf', 'oneOf', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('not', 'not', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('discriminator', 'discriminator', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('properties', 'properties', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('additionalProperties', 'additionalProperties', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('unevaluatedProperties', 'unevaluatedProperties', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('items', 'items', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('prefixItems', 'prefixItems', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('contains', 'contains', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('minContains', 'minContains', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('maxContains', 'maxContains', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('patternProperties', 'patternProperties', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('propertyNames', 'propertyNames', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('dependentSchemas', 'dependentSchemas', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('if', 'if', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('then', 'then', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('else', 'else', FieldMetadata::CATEGORY_COMPOSITION),
            new FieldMetadata('unevaluatedItems', 'unevaluatedItems', FieldMetadata::CATEGORY_ARRAY),
            new FieldMetadata('example', 'example', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('examples', 'examples', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('enum', 'enum', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('format', 'format', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('contentEncoding', 'contentEncoding', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('contentMediaType', 'contentMediaType', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('contentSchema', 'contentSchema', FieldMetadata::CATEGORY_OBJECT),
            new FieldMetadata('jsonSchemaDialect', '$schema', FieldMetadata::CATEGORY_FLAT),
            new FieldMetadata('xml', 'xml', FieldMetadata::CATEGORY_FLAT),
        ];
    }
}
