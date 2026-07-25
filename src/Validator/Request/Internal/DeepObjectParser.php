<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use JsonException;

use function count;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class DeepObjectParser
{
    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;

    /**
     * @param non-empty-list<array{rawKey: string, rawValue: string, decodedKey: string}> $groupPairs
     *
     * @return string|array<array-key, mixed>
     */
    public function resolveGroup(string $baseKey, array $groupPairs, BracketNotationParser $bracketParser): string|array
    {
        $allScalar = array_all($groupPairs, fn($pair) => !($pair['decodedKey'] !== $baseKey));
        if ($allScalar) {
            return $this->collectScalarValues($groupPairs);
        }

        return $this->buildNestedTree($groupPairs, $baseKey, $bracketParser);
    }

    public function parseByMediaType(string $raw, string $mediaType, string $parameterName): mixed
    {
        if ('application/json' === $mediaType) {
            return $this->parseJson($raw, $parameterName);
        }

        if ('text/plain' === $mediaType) {
            return $raw;
        }

        throw new UnsupportedMediaTypeException($mediaType, ['application/json', 'text/plain']);
    }

    /**
     * @param non-empty-list<array{rawKey: string, rawValue: string, decodedKey: string}> $groupPairs
     *
     * @return string|array<int, string>
     */
    private function collectScalarValues(array $groupPairs): string|array
    {
        $values = [];
        foreach ($groupPairs as $pair) {
            $values[] = urldecode($pair['rawValue']);
        }

        return 1 === count($values) ? $values[0] : $values;
    }

    /**
     * @param non-empty-list<array{rawKey: string, rawValue: string, decodedKey: string}> $groupPairs
     *
     * @return string|array<array-key, mixed>
     */
    private function buildNestedTree(array $groupPairs, string $baseKey, BracketNotationParser $bracketParser): string|array
    {
        $tree = [];
        foreach ($groupPairs as $pair) {
            $tree = $bracketParser->insertNested($tree, $pair['decodedKey'], urldecode($pair['rawValue']));
        }

        /** @var string|array<array-key, mixed> $value */
        $value = $tree[$baseKey] ?? '';

        return $value;
    }

    private function parseJson(string $raw, string $parameterName): mixed
    {
        try {
            $decoded = rawurldecode($raw);

            return json_decode($decoded, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidParameterException::malformedValue(
                $parameterName,
                'Invalid JSON: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
