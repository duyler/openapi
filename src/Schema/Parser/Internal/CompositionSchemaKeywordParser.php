<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

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
        $accumulator = new SchemaKeywordAccumulator($recurse);

        return [
            'ref' => TypeHelper::asStringOrNull($data['$ref'] ?? null),
            'refSummary' => $this->refSummary($data),
            'refDescription' => $this->refDescription($data),
            'allOf' => $accumulator->schemaList($data, 'allOf'),
            'anyOf' => $accumulator->schemaList($data, 'anyOf'),
            'oneOf' => $accumulator->schemaList($data, 'oneOf'),
            'not' => $accumulator->schemaOrBoolOrNull($data, 'not'),
            'discriminator' => isset($data['discriminator'])
                ? $this->buildDiscriminator(TypeHelper::asArray($data['discriminator']))
                : null,
            'if' => $accumulator->schemaOrBoolOrNull($data, 'if'),
            'then' => $accumulator->schemaOrBoolOrNull($data, 'then'),
            'else' => $accumulator->schemaOrBoolOrNull($data, 'else'),
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
}
