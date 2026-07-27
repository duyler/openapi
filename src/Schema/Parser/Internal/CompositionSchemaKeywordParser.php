<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;
use Duyler\OpenApi\Validator\TypeFormatter;

use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function sprintf;

/** @internal */
final readonly class CompositionSchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     *
     * @return array{
     *     ref: ?string,
     *     refSummary: ?string,
     *     refDescription: ?string,
     *     allOf: ?list<Schema>,
     *     anyOf: ?list<Schema>,
     *     oneOf: ?list<Schema>,
     *     not: Schema|bool|null,
     *     discriminator: ?Discriminator,
     *     if: Schema|bool|null,
     *     then: Schema|bool|null,
     *     else: Schema|bool|null,
     * }
     */
    public function build(array $data, Closure $recurse): array
    {
        return [
            'ref' => TypeHelper::asStringOrNull($data['$ref'] ?? null),
            'refSummary' => $this->refSummary($data),
            'refDescription' => $this->refDescription($data),
            'allOf' => $this->buildSchemaList($data, 'allOf', $recurse),
            'anyOf' => $this->buildSchemaList($data, 'anyOf', $recurse),
            'oneOf' => $this->buildSchemaList($data, 'oneOf', $recurse),
            'not' => $this->schemaOrBoolOrNull($data, 'not', $recurse),
            'discriminator' => isset($data['discriminator'])
                ? $this->buildDiscriminator(TypeHelper::asArray($data['discriminator']))
                : null,
            'if' => $this->schemaOrBoolOrNull($data, 'if', $recurse),
            'then' => $this->schemaOrBoolOrNull($data, 'then', $recurse),
            'else' => $this->schemaOrBoolOrNull($data, 'else', $recurse),
        ];
    }

    /** @param array<array-key, mixed> $data */
    private function refSummary(array $data): ?string
    {
        if (false === isset($data['$ref'])) {
            return null;
        }

        return TypeHelper::asStringOrNull($data['summary'] ?? null);
    }

    /** @param array<array-key, mixed> $data */
    private function refDescription(array $data): ?string
    {
        if (false === isset($data['$ref'])) {
            return null;
        }

        return TypeHelper::asStringOrNull($data['description'] ?? null);
    }

    /** @param array<string, mixed> $data */
    private function buildDiscriminator(array $data): Discriminator
    {
        return new Discriminator(
            propertyName: TypeHelper::asStringOrNull($data['propertyName'] ?? null),
            mapping: TypeHelper::asStringMapOrNull($data['mapping'] ?? null),
            defaultMapping: TypeHelper::asStringOrNull($data['defaultMapping'] ?? null),
        );
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
