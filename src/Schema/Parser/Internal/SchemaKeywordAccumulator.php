<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\TypeFormatter;

use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function sprintf;

/** @internal */
final readonly class SchemaKeywordAccumulator
{
    /**
     * @param Closure(bool|array): Schema $recurse
     */
    public function __construct(
        private readonly Closure $recurse,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public function schemaOrBoolOrNull(array $data, string $key): Schema|bool|null
    {
        if (false === isset($data[$key])) {
            return null;
        }

        if (is_bool($data[$key])) {
            return $data[$key];
        }

        if (is_array($data[$key])) {
            return ($this->recurse)($data[$key]);
        }

        throw new InvalidSchemaException(sprintf(
            'Expected array or boolean for schema keyword "%s", got %s',
            $key,
            TypeFormatter::format($data[$key]),
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     * @return ?list<Schema>
     */
    public function schemaList(array $data, string $key): ?array
    {
        if (false === isset($data[$key])) {
            return null;
        }

        if (false === is_array($data[$key])) {
            throw new InvalidSchemaException(sprintf(
                'Expected array for schema keyword "%s", got %s',
                $key,
                TypeFormatter::format($data[$key]),
            ));
        }

        $items = $data[$key];

        return array_values(array_map(
            function (mixed $schemaData) use ($key): Schema {
                if (is_array($schemaData) || is_bool($schemaData)) {
                    return ($this->recurse)($schemaData);
                }

                throw new InvalidSchemaException(sprintf(
                    'Expected array or boolean for schema keyword "%s", got %s',
                    $key,
                    TypeFormatter::format($schemaData),
                ));
            },
            $items,
        ));
    }
}
