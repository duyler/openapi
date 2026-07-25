<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal
 */
final readonly class ObjectFields
{
    /**
     * @param array<string, Schema>|null  $properties
     * @param list<string>|null           $required
     * @param array<string, Schema>|null  $patternProperties
     * @param array<string, Schema>|null  $dependentSchemas
     */
    public function __construct(
        public ?array $properties = null,
        public ?array $required = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public Schema|bool|null $additionalProperties = null,
        public Schema|bool|null $unevaluatedProperties = null,
        public ?array $patternProperties = null,
        public ?array $dependentSchemas = null,
        public Schema|bool|null $propertyNames = null,
    ) {}
}
