<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function array_map;
use function implode;
use function is_array;
use function is_string;
use function strval;

final readonly class HeaderFinder
{
    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     */
    public function find(array $headers, string $name): ?string
    {
        /** @var array<array-key, string|array<array-key, string>> $headers */
        foreach ($headers as $key => $value) {
            if (false === is_string($key)) {
                continue;
            }

            if (strtolower($key) === strtolower($name)) {
                if (is_array($value)) {
                    $stringValue = implode(', ', array_map(strval(...), $value));

                    return $stringValue;
                }

                if (is_string($value)) {
                    return $value;
                }

                return null;
            }
        }

        return null;
    }
}
