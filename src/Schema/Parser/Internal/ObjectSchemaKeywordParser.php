<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

use function is_array;
use function is_bool;

/** @internal */
final readonly class ObjectSchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     *
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
        return [
            'required' => TypeHelper::asStringListOrNull($data['required'] ?? null),
            'properties' => isset($data['properties']) && is_array($data['properties'])
                ? $this->buildProperties(TypeHelper::asArray($data['properties']), $recurse)
                : null,
            'additionalProperties' => $this->buildOptionalSchema($data, 'additionalProperties', $recurse),
            'unevaluatedProperties' => $this->buildOptionalSchema($data, 'unevaluatedProperties', $recurse),
            'maxProperties' => TypeHelper::asIntOrNull($data['maxProperties'] ?? null),
            'minProperties' => TypeHelper::asIntOrNull($data['minProperties'] ?? null),
            'patternProperties' => isset($data['patternProperties']) && is_array($data['patternProperties'])
                ? $this->buildProperties(TypeHelper::asArray($data['patternProperties']), $recurse)
                : null,
            'propertyNames' => $this->schemaOrBoolOrNull($data, 'propertyNames', $recurse),
            'dependentSchemas' => isset($data['dependentSchemas']) && is_array($data['dependentSchemas'])
                ? $this->buildProperties(TypeHelper::asArray($data['dependentSchemas']), $recurse)
                : null,
            'contentSchema' => $this->buildOptionalSchema($data, 'contentSchema', $recurse),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     */
    private function buildOptionalSchema(array $data, string $key, Closure $recurse): Schema|bool|null
    {
        if (false === isset($data[$key])) {
            return null;
        }

        if (is_array($data[$key])) {
            return $recurse(TypeHelper::asArray($data[$key]));
        }

        return (bool) $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     */
    private function schemaOrBoolOrNull(array $data, string $key, Closure $recurse): Schema|bool|null
    {
        if (false === isset($data[$key])) {
            return null;
        }

        $value = $data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $recurse($value);
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     *
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
