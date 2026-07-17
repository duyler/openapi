<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use function version_compare;

/**
 * Shared mutable parsing context for the OpenAPI parser pipeline.
 *
 * Carries the document version (set by OpenApiBuilder::buildDocument once the
 * root object has been validated), the DeprecationLogger, and the five
 * sub-builder instances. Sub-builders reach their siblings through this
 * object to resolve cross-object references (e.g. PathItemBuilder needs
 * ComponentsBuilder for request bodies, ComponentsBuilder needs SchemaBuilder
 * for component schemas).
 *
 * The constructor bootstraps the sub-builder graph atomically: it constructs
 * each sub-builder against itself and assigns the references back onto the
 * public properties. After construction every sub-builder field is safe to
 * read.
 */
final class OpenApiBuildContext
{
    private const string DEPRECATION_VERSION = '3.2.0';

    public string $documentVersion = '';

    public InfoBuilder $infoBuilder;
    public SchemaBuilder $schemaBuilder;
    public SecuritySchemeBuilder $securitySchemeBuilder;
    public PathItemBuilder $pathItemBuilder;
    public ComponentsBuilder $componentsBuilder;

    public function __construct(
        public readonly DeprecationLogger $deprecationLogger = new DeprecationLogger(),
    ) {
        $this->infoBuilder = new InfoBuilder($this);
        $this->schemaBuilder = new SchemaBuilder($this);
        $this->securitySchemeBuilder = new SecuritySchemeBuilder($this);
        $this->pathItemBuilder = new PathItemBuilder($this);
        $this->componentsBuilder = new ComponentsBuilder($this);
    }

    public function shouldWarnDeprecation(): bool
    {
        return version_compare($this->documentVersion, self::DEPRECATION_VERSION, '>=');
    }

    public function isVersion30(): bool
    {
        return version_compare($this->documentVersion, '3.1.0', '<');
    }
}
