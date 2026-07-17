<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use JsonException;

use function array_key_last;
use function array_slice;
use function count;
use function is_array;
use function sprintf;
use function substr_count;

use const JSON_THROW_ON_ERROR;

final readonly class QueryParser
{
    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;

    private const int MAX_NESTING_DEPTH = 64;

    private const int MAX_QUERY_PAIRS = 1000;

    /**
     * Uses an explicit segment-based parser (insertNested + assignSegments) instead
     * of parse_str because parse_str converts dots in keys to underscores
     * (e.g. `user.name` becomes `user_name`) and keeps only the last value for
     * duplicate scalar keys. This parser preserves key names literally and
     * collects duplicate scalars into indexed arrays to match the structure
     * produced by RFC 6570 form-style explode expansion.
     *
     * @return array<array-key, mixed>
     */
    public function parse(string $queryString): array
    {
        if ('' === $queryString) {
            return [];
        }

        if (substr_count($queryString, '&') + 1 > self::MAX_QUERY_PAIRS) {
            throw new InvalidParameterException(
                'query',
                sprintf('Maximum query string pairs of %d exceeded', self::MAX_QUERY_PAIRS),
            );
        }

        /** @var array<string, non-empty-list<array{rawKey: string, rawValue: string, decodedKey: string}>> $groups */
        $groups = [];
        foreach (explode('&', $queryString) as $rawPair) {
            if ('' === $rawPair) {
                continue;
            }

            ['rawKey' => $rawKey, 'rawValue' => $rawValue] = $this->splitRawPair($rawPair);
            $decodedKey = urldecode($rawKey);
            $baseKey = $this->extractBaseKey($decodedKey);

            $groups[$baseKey][] = [
                'rawKey' => $rawKey,
                'rawValue' => $rawValue,
                'decodedKey' => $decodedKey,
            ];
        }

        /** @var array<array-key, mixed> $result */
        $result = [];
        foreach ($groups as $baseKey => $groupPairs) {
            $result[$baseKey] = $this->resolveGroup($baseKey, $groupPairs);
        }

        return $result;
    }

    /**
     * @param array<int, scalar> $values
     * @return array<int, scalar>|string
     */
    public function handleExplode(array $values, bool $explode): array|string
    {
        if (false === $explode) {
            return implode(',', $values);
        }

        return $values;
    }

    public function parseQueryString(string $rawQueryString, Parameter $parameter): mixed
    {
        if ('querystring' !== $parameter->in) {
            return null;
        }

        $content = $parameter->content;
        if (null === $content) {
            return null;
        }

        foreach ($content->mediaTypes as $mediaType => $mediaTypeObject) {
            return $this->parseByMediaType($rawQueryString, $mediaType, $mediaTypeObject, $parameter->name ?? 'unknown');
        }

        return null;
    }

    /**
     * @return array{rawKey: string, rawValue: string}
     */
    private function splitRawPair(string $rawPair): array
    {
        $equalsPos = strpos($rawPair, '=');
        if (false === $equalsPos) {
            return ['rawKey' => $rawPair, 'rawValue' => ''];
        }

        return [
            'rawKey' => substr($rawPair, 0, $equalsPos),
            'rawValue' => substr($rawPair, $equalsPos + 1),
        ];
    }

    private function extractBaseKey(string $decodedKey): string
    {
        $bracketPos = strpos($decodedKey, '[');

        return false === $bracketPos ? $decodedKey : substr($decodedKey, 0, $bracketPos);
    }

    /**
     * @param non-empty-list<array{rawKey: string, rawValue: string, decodedKey: string}> $groupPairs
     *
     * @return string|array<array-key, mixed>
     */
    private function resolveGroup(string $baseKey, array $groupPairs): string|array
    {
        $allScalar = true;
        foreach ($groupPairs as $pair) {
            if ($pair['decodedKey'] !== $baseKey) {
                $allScalar = false;
                break;
            }
        }

        if ($allScalar) {
            $values = [];
            foreach ($groupPairs as $pair) {
                $values[] = urldecode($pair['rawValue']);
            }

            return 1 === count($values) ? $values[0] : $values;
        }

        $tree = [];
        foreach ($groupPairs as $pair) {
            $tree = $this->insertNested($tree, $pair['decodedKey'], urldecode($pair['rawValue']));
        }

        /** @var string|array<array-key, mixed> $value */
        $value = $tree[$baseKey] ?? '';

        return $value;
    }

    /**
     * Parse a key like "tags[a][b][]" into segments and assign value to a nested tree.
     *
     * The early substr_count bound prevents OOM via huge $rest reaching preg_match_all
     * below — substr_count is O(n) and allocates nothing, while preg_match_all
     * materializes the full match-set into memory before our depth guard can fire.
     *
     * @param array<array-key, mixed> $tree
     * @return array<array-key, mixed>
     */
    private function insertNested(array $tree, string $key, string $value): array
    {
        if (1 !== preg_match('/^(?<root>[^\[]+)(?<rest>.*)$/', $key, $m)) {
            return $tree;
        }

        /** @var string $rootName */
        $rootName = $m['root'];
        /** @var string $rest */
        $rest = $m['rest'];

        if ('' === $rest) {
            $tree[$rootName] = $value;

            return $tree;
        }

        if (substr_count($rest, '[') > self::MAX_NESTING_DEPTH) {
            throw new InvalidParameterException(
                $rootName,
                sprintf('Maximum query parameter nesting depth of %d exceeded', self::MAX_NESTING_DEPTH),
            );
        }

        $segments = [$rootName];
        if (preg_match_all('/\[(?<key>[^\[\]]*)\]/', $rest, $bracketMatches) > 0) {
            /** @var list<string> $bracketKeys */
            $bracketKeys = $bracketMatches['key'];
            foreach ($bracketKeys as $bk) {
                $segments[] = $bk;
            }
        }

        if (count($segments) > self::MAX_NESTING_DEPTH) {
            throw new InvalidParameterException(
                $rootName,
                sprintf('Maximum query parameter nesting depth of %d exceeded', self::MAX_NESTING_DEPTH),
            );
        }

        return $this->assignSegments($tree, $segments, $value);
    }

    /**
     * Walk segments and assign value. Empty segment means numeric-indexed append.
     *
     * @param array<array-key, mixed> $node
     * @param non-empty-list<string> $segments
     * @return array<array-key, mixed>
     */
    private function assignSegments(array $node, array $segments, string $value): array
    {
        $segment = $segments[0];
        /** @var list<string> $remaining */
        $remaining = array_slice($segments, 1);

        if ('' === $segment) {
            if ([] === $remaining) {
                $node[] = $value;

                return $node;
            }

            $node[] = [];
            $lastKey = array_key_last($node);
            /** @var array<array-key, mixed> $child */
            $child = $node[$lastKey];
            $node[$lastKey] = $this->assignSegments($child, $remaining, $value);

            return $node;
        }

        if ([] === $remaining) {
            $node[$segment] = $value;

            return $node;
        }

        if (false === is_array($node[$segment] ?? null)) {
            $node[$segment] = [];
        }
        /** @var array<array-key, mixed> $child */
        $child = $node[$segment];
        $node[$segment] = $this->assignSegments($child, $remaining, $value);

        return $node;
    }

    private function parseByMediaType(string $raw, string $mediaType, MediaType $mediaTypeObject, string $parameterName): mixed
    {
        if ('application/json' === $mediaType) {
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

        if ('text/plain' === $mediaType) {
            return $raw;
        }

        throw new UnsupportedMediaTypeException($mediaType, ['application/json', 'text/plain']);
    }
}
