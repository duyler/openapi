<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;

use function explode;
use function rawurldecode;
use function sprintf;
use function strpos;
use function substr;
use function substr_count;
use function trim;

/** @internal */
final readonly class CookieHeaderParser
{
    private const int MAX_COOKIE_PAIRS = 100;

    /**
     * @return array<string, string>
     */
    public function parsePairs(string $header): array
    {
        if ('' === trim($header)) {
            return [];
        }

        $pairs = [];

        foreach ($this->splitPairs($header) as $pair) {
            $pairs[$pair['name']] = $pair['value'] ?? '';
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    public function parseExplodedValues(string $header, string $name): array
    {
        $values = [];

        foreach ($this->splitPairs($header) as $pair) {
            if ($pair['name'] === $name && null !== $pair['value']) {
                $values[] = rawurldecode($pair['value']);
            }
        }

        return $values;
    }

    /**
     * @return list<array{name: string, value: string|null}>
     */
    private function splitPairs(string $header): array
    {
        $this->enforcePairCountLimit($header);

        $pairs = [];

        foreach (explode(';', $header) as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }

            $equalsPos = strpos($part, '=');

            if (false === $equalsPos) {
                $pairs[] = ['name' => $part, 'value' => null];
                continue;
            }

            $pairs[] = [
                'name' => substr($part, 0, $equalsPos),
                'value' => substr($part, $equalsPos + 1),
            ];
        }

        return $pairs;
    }

    private function enforcePairCountLimit(string $header): void
    {
        if (self::MAX_COOKIE_PAIRS < substr_count($header, ';') + 1) {
            throw new InvalidParameterException(
                'cookie',
                sprintf('Maximum cookie pairs of %d exceeded', self::MAX_COOKIE_PAIRS),
            );
        }
    }
}
