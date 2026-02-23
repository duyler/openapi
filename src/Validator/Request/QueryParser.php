<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use JsonException;

use const JSON_THROW_ON_ERROR;

readonly class QueryParser
{
    /**
     * Parse query string into parameters
     *
     * @return array<array-key, mixed>
     */
    public function parse(string $queryString): array
    {
        if ('' === $queryString) {
            return [];
        }

        $params = [];
        parse_str($queryString, $params);

        return $params;
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

    private function parseByMediaType(string $raw, string $mediaType, MediaType $mediaTypeObject, string $parameterName): mixed
    {
        if ('application/json' === $mediaType) {
            try {
                $decoded = rawurldecode($raw);
                return json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
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
