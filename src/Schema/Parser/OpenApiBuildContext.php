<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use function version_compare;

/**
 * @internal Parser-internal builder context; not part of the public API.
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
}
