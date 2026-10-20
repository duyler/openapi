<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Compiler\Exception\ComponentRefResolutionException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;

use function str_starts_with;
use function strlen;
use function substr;

/** @internal */
final readonly class ComponentRefResolver
{
    public const string REF_COMPONENTS_SCHEMAS_PREFIX = '#/components/schemas/';

    public function resolve(string $ref, OpenApiDocument $document): Schema
    {
        if (false === str_starts_with($ref, self::REF_COMPONENTS_SCHEMAS_PREFIX)) {
            throw new ComponentRefResolutionException(ref: $ref, schemaName: null);
        }

        $schemaName = substr($ref, strlen(self::REF_COMPONENTS_SCHEMAS_PREFIX));
        $schemas = $document->components?->schemas ?? [];

        if (false === isset($schemas[$schemaName])) {
            throw new ComponentRefResolutionException(ref: $ref, schemaName: $schemaName);
        }

        return $schemas[$schemaName];
    }
}
