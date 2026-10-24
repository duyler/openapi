<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use function count;
use function explode;
use function str_replace;
use function strtolower;
use function trim;

/** @internal */
final readonly class PartHeaders
{
    /**
     * @return array<string, string>
     */
    public static function map(string $rawHeaders): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $rawHeaders));

        $headers = [];

        foreach ($lines as $line) {
            $pair = explode(':', $line, 2);

            if (2 !== count($pair)) {
                continue;
            }

            $headers[strtolower(trim($pair[0]))] = trim($pair[1]);
        }

        return $headers;
    }
}
