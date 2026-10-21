<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

use function is_array;

/** @internal */
final readonly class ObjectSchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     * @return array{
     *     required: ?list<string>,
     *     properties: ?array<string, Schema>,
     *     additionalProperties: Schema|bool|null,
     *     unevaluatedProperties: Schema|bool|null,
     *     maxProperties: ?int,
     *     minProperties: ?int,
     *     patternProperties: ?array<string, Schema>,
     *     propertyNames: Schema|bool|null,
     *     dependentSchemas: ?array<string, Schema>,
     *     contentSchema: Schema|bool|null,
     * }
     */
    public function build(array $data, Closure $recurse): array
    {
        $accumulator = new SchemaKeywordAccumulator($recurse);

        return [
            'required' => TypeHelper::asStringListOrNull($data['required'] ?? null),
            'properties' => isset($data['properties']) && is_array($data['properties'])
                ? $this->buildProperties(TypeHelper::asArray($data['properties']), $recurse)
                : null,
            'additionalProperties' => $accumulator->schemaOrBoolOrNull($data, 'additionalProperties'),
            'unevaluatedProperties' => $accumulator->schemaOrBoolOrNull($data, 'unevaluatedProperties'),
            'maxProperties' => TypeHelper::asIntOrNull($data['maxProperties'] ?? null),
            'minProperties' => TypeHelper::asIntOrNull($data['minProperties'] ?? null),
            'patternProperties' => isset($data['patternProperties']) && is_array($data['patternProperties'])
                ? $this->buildProperties(TypeHelper::asArray($data['patternProperties']), $recurse)
                : null,
            'propertyNames' => $accumulator->schemaOrBoolOrNull($data, 'propertyNames'),
            'dependentSchemas' => isset($data['dependentSchemas']) && is_array($data['dependentSchemas'])
                ? $this->buildProperties(TypeHelper::asArray($data['dependentSchemas']), $recurse)
                : null,
            'contentSchema' => $accumulator->schemaOrBoolOrNull($data, 'contentSchema'),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     * @return array<string, Schema>
     */
    private function buildProperties(array $data, Closure $recurse): array
    {
        $properties = [];

        foreach ($data as $name => $schema) {
            $properties[$name] = $recurse($schema);
        }

        return $properties;
    }
}
