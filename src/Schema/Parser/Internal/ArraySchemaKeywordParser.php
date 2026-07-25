<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;
use Duyler\OpenApi\Validator\TypeFormatter;

use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function sprintf;

/** @internal */
final readonly class ArraySchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     *
     * @return array{
     *     items: Schema|bool|null,
     *     prefixItems: ?list<Schema>,
     *     contains: Schema|bool|null,
     *     minContains: ?int,
     *     maxContains: ?int,
     *     minItems: ?int,
     *     maxItems: ?int,
     *     uniqueItems: ?bool,
     *     unevaluatedItems: Schema|bool|null,
     * }
     */
    public function build(array $data, Closure $recurse): array
    {
        return [
            'items' => $this->schemaOrBoolOrNull($data, 'items', $recurse),
            'prefixItems' => $this->buildSchemaList($data, 'prefixItems', $recurse),
            'contains' => $this->schemaOrBoolOrNull($data, 'contains', $recurse),
            'minContains' => TypeHelper::asIntOrNull($data['minContains'] ?? null),
            'maxContains' => TypeHelper::asIntOrNull($data['maxContains'] ?? null),
            'minItems' => TypeHelper::asIntOrNull($data['minItems'] ?? null),
            'maxItems' => TypeHelper::asIntOrNull($data['maxItems'] ?? null),
            'uniqueItems' => TypeHelper::asBoolOrNull($data['uniqueItems'] ?? null),
            'unevaluatedItems' => $this->schemaOrBoolOrNull($data, 'unevaluatedItems', $recurse),
        ];
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

        throw new InvalidSchemaException(sprintf(
            'Expected array or boolean for schema keyword "%s", got %s',
            $key,
            TypeFormatter::format($value),
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     *
     * @return ?list<Schema>
     */
    private function buildSchemaList(array $data, string $key, Closure $recurse): ?array
    {
        if (false === isset($data[$key])) {
            return null;
        }

        $items = TypeHelper::asArray($data[$key]);

        return array_values(array_map(
            static function (mixed $schemaData) use ($recurse): Schema {
                if (is_array($schemaData) || is_bool($schemaData)) {
                    return $recurse($schemaData);
                }

                throw new InvalidSchemaException(
                    sprintf('Expected array or boolean for schema, got %s', TypeFormatter::format($schemaData)),
                );
            },
            $items,
        ));
    }
}
