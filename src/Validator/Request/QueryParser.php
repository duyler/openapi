<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use JsonException;

use function count;

use const JSON_THROW_ON_ERROR;

final readonly class QueryParser
{
    private const int JSON_MAX_DEPTH = 512;

    /**
     * Parse query string into parameters.
     *
     * Duplicate scalar keys (e.g. `tags=php&tags=go`) are collected into
     * indexed arrays to comply with RFC 6570 form+explode semantics, since
     * PHP 8.5 `parse_str` no longer performs this conversion and keeps only
     * the last value as a scalar.
     *
     * @return array<array-key, mixed>
     */
    public function parse(string $queryString): array
    {
        if ('' === $queryString) {
            return [];
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
     * Handle explode parameter format
     *
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

        $rebuilt = [];
        foreach ($groupPairs as $pair) {
            $rebuilt[] = $pair['rawKey'] . '=' . $pair['rawValue'];
        }

        $parsed = [];
        parse_str(implode('&', $rebuilt), $parsed);

        /** @var string|array<array-key, mixed> $value */
        $value = $parsed[$baseKey] ?? '';

        return $value;
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
