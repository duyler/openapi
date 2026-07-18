<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

/**
 * Single source of truth for the list of JSON Schema 2020-12 / OpenAPI 3.2
 * fields exposed by {@see Schema}.
 *
 * Adding a new field to `Schema` requires only adding a row here. The
 * SchemaToArrayConverter and SchemaFromArrayConverter read this catalog and
 * serialise / parse the field uniformly.
 *
 * Field categories:
 * - `flat`   - lives directly on the Schema facade (scalars, nested Schema,
 *              Xml, Discriminator, etc.).
 * - `string` / `numeric` / `array` / `object` / `composition` - the field
 *              belongs to the matching sub-DTO; `Schema::stringConstraints()`
 *              and friends expose them as a typed group.
 *
 * The `openApiName` is the wire key used in the JSON / YAML form. `$ref` and
 * `$schema` are written verbatim, the rest match PHP property names.
 */
final readonly class FieldMetadata
{
    public const string CATEGORY_FLAT = 'flat';
    public const string CATEGORY_STRING = 'string';
    public const string CATEGORY_NUMERIC = 'numeric';
    public const string CATEGORY_ARRAY = 'array';
    public const string CATEGORY_OBJECT = 'object';
    public const string CATEGORY_COMPOSITION = 'composition';

    public function __construct(
        public string $name,
        public string $openApiName,
        public string $category,
        public bool $hasSentinel = false,
    ) {}
}
