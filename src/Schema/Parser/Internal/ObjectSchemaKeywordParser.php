<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;
use Duyler\OpenApi\Validator\TypeFormatter;

use function array_is_list;
use function array_keys;
use function array_merge;
use function is_array;
use function sprintf;
use function is_bool;

/** @internal */
final readonly class ObjectSchemaKeywordParser
{
    private const string LEGACY_DEPENDENCIES = 'dependencies';

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
     *     dependentRequired: ?array<string, list<string>>,
     *     dependentSchemas: ?array<string, Schema>,
     *     contentSchema: Schema|bool|null,
     * }
     */
    public function build(array $data, Closure $recurse): array
    {
        $accumulator = new SchemaKeywordAccumulator($recurse);
        $dependencies = $this->buildLegacyDependencies($data, $recurse);

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
            'dependentRequired' => $this->mergeDependentRequired(
                $this->buildDependentRequired($data),
                $dependencies['dependentRequired'],
            ),
            'dependentSchemas' => $this->mergeDependentSchemas(
                isset($data['dependentSchemas']) && is_array($data['dependentSchemas'])
                    ? $this->buildProperties(TypeHelper::asArray($data['dependentSchemas']), $recurse)
                    : null,
                $dependencies['dependentSchemas'],
            ),
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

    /**
     * @param array<array-key, mixed> $data
     * @return ?array<string, list<string>>
     */
    private function buildDependentRequired(array $data): ?array
    {
        if (false === isset($data['dependentRequired']) || false === is_array($data['dependentRequired'])) {
            return null;
        }

        /** @var array<string, list<string>> $map */
        $map = [];

        $raw = TypeHelper::asArray($data['dependentRequired']);

        foreach (array_keys($raw) as $property) {
            $map[(string) $property] = TypeHelper::asStringList($raw[$property]);
        }

        return [] === $map ? null : $map;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     * @return array{dependentRequired: ?array<string, list<string>>, dependentSchemas: ?array<string, Schema>}
     */
    private function buildLegacyDependencies(array $data, Closure $recurse): array
    {
        if (false === isset($data[self::LEGACY_DEPENDENCIES]) || false === is_array($data[self::LEGACY_DEPENDENCIES])) {
            return ['dependentRequired' => null, 'dependentSchemas' => null];
        }

        $dependentRequired = [];
        $dependentSchemas = [];

        foreach (TypeHelper::asArray($data[self::LEGACY_DEPENDENCIES]) as $property => $dependency) {
            $name = (string) $property;

            if (is_array($dependency) && array_is_list($dependency)) {
                foreach (TypeHelper::asStringList($dependency) as $requiredProperty) {
                    $dependentRequired[$name][] = $requiredProperty;
                }

                continue;
            }

            if (false === is_array($dependency) && false === is_bool($dependency)) {
                throw new InvalidSchemaException(sprintf(
                    'Expected array, boolean or list of strings for schema keyword "dependencies", got %s',
                    TypeFormatter::format($dependency),
                ));
            }

            $dependentSchemas[$name] = $recurse($dependency);
        }

        return [
            'dependentRequired' => [] === $dependentRequired ? null : $dependentRequired,
            'dependentSchemas' => [] === $dependentSchemas ? null : $dependentSchemas,
        ];
    }

    /**
     * @param ?array<string, list<string>> $explicit
     * @param ?array<string, list<string>> $legacy
     * @return ?array<string, list<string>>
     */
    private function mergeDependentRequired(?array $explicit, ?array $legacy): ?array
    {
        if (null === $legacy) {
            return $explicit;
        }

        if (null === $explicit) {
            return $legacy;
        }

        $merged = $explicit;

        foreach ($legacy as $property => $requiredProperties) {
            $merged[$property] = array_values(array_unique(array_merge(
                $merged[$property] ?? [],
                $requiredProperties,
            )));
        }

        return $merged;
    }

    /**
     * @param ?array<string, Schema> $explicit
     * @param ?array<string, Schema> $legacy
     * @return ?array<string, Schema>
     */
    private function mergeDependentSchemas(?array $explicit, ?array $legacy): ?array
    {
        if (null === $legacy) {
            return $explicit;
        }

        if (null === $explicit) {
            return $legacy;
        }

        return array_merge($explicit, $legacy);
    }
}
