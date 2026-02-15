<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

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
}
