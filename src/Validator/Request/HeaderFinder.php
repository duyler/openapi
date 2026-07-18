<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function implode;
use function is_array;
use function is_string;
use function strtolower;

final readonly class HeaderFinder
{
    /**
     * @param array<array-key, string|array<array-key, string>> $headers
     */
    public function find(array $headers, string $name): ?string
    {
        $normalizedName = strtolower($name);

        foreach ($headers as $key => $value) {
            if (false === is_string($key)) {
                continue;
            }

            if (strtolower($key) === $normalizedName) {
                if (is_array($value)) {
                    return implode(',', $value);
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
