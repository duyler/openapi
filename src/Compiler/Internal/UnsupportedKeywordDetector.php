<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function array_filter;
use function array_keys;
use function array_unique;
use function array_values;
use function is_bool;

/**
 * Walks the schema tree and reports any keyword that the ValidatorCompiler
 * does not generate code for. The detector is a precondition check: it
 * runs before code generation so the compiler throws
 * UnsupportedKeywordException rather than silently emitting a validator
 * that ignores the keyword. Detection is recursive — unsupported
 * keywords inside nested `properties` or `items` are also reported.
 */
final readonly class UnsupportedKeywordDetector
{
    /**
     * @return list<string>
     */
    public function detect(Schema $schema): array
    {
        $detected = array_keys(array_filter([
            'allOf' => null !== $schema->allOf,
            'anyOf' => null !== $schema->anyOf,
            'oneOf' => null !== $schema->oneOf,
            'not' => null !== $schema->not,
            'if' => null !== $schema->if,
            'then' => null !== $schema->then,
            'else' => null !== $schema->else,
            'patternProperties' => null !== $schema->patternProperties,
            'format' => null !== $schema->format,
            'minProperties' => null !== $schema->minProperties,
            'maxProperties' => null !== $schema->maxProperties,
            'additionalProperties' => $schema->additionalProperties instanceof Schema,
            'prefixItems' => null !== $schema->prefixItems,
            'contains' => null !== $schema->contains,
            'propertyNames' => null !== $schema->propertyNames,
            'unevaluatedItems' => null !== $schema->unevaluatedItems,
            'unevaluatedProperties' => null !== $schema->unevaluatedProperties,
            'dependentSchemas' => null !== $schema->dependentSchemas,
            'discriminator' => null !== $schema->discriminator,
            'contentEncoding' => null !== $schema->contentEncoding,
            'contentMediaType' => null !== $schema->contentMediaType,
            'contentSchema' => null !== $schema->contentSchema,
            'items (boolean form)' => is_bool($schema->items),
            'contains (boolean form)' => is_bool($schema->contains),
            'propertyNames (boolean form)' => is_bool($schema->propertyNames),
            'if (boolean form)' => is_bool($schema->if),
            'then (boolean form)' => is_bool($schema->then),
            'else (boolean form)' => is_bool($schema->else),
            'not (boolean form)' => is_bool($schema->not),
            'unevaluatedItems (boolean form)' => is_bool($schema->unevaluatedItems),
        ]));

        foreach ($schema->properties ?? [] as $propertySchema) {
            $detected = [...$detected, ...$this->detect($propertySchema)];
        }

        if ($schema->items instanceof Schema) {
            $detected = [...$detected, ...$this->detect($schema->items)];
        }

        return array_values(array_unique($detected));
    }
}
