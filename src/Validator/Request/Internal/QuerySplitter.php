<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

/** @internal */
final readonly class QuerySplitter
{
    /**
     * @return array{rawKey: string, rawValue: string}
     */
    public function splitRawPair(string $rawPair): array
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

    public function extractBaseKey(string $decodedKey): string
    {
        $bracketPos = strpos($decodedKey, '[');

        return false === $bracketPos ? $decodedKey : substr($decodedKey, 0, $bracketPos);
    }
}
