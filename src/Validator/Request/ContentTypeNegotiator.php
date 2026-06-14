<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;

use function count;
use function explode;
use function preg_match;
use function strtolower;
use function trim;

final readonly class ContentTypeNegotiator
{
    public function getMediaType(string $contentType): string
    {
        $parts = explode(';', $contentType);
        $mediaType = strtolower(trim($parts[0]));

        $qValue = $this->extractQValue($parts);

        if (0.0 === $qValue) {
            throw new UnsupportedMediaTypeException($mediaType, []);
        }

        return $mediaType;
    }

    /**
     * @param list<string> $parts
     */
    private function extractQValue(array $parts): float
    {
        if (1 === count($parts)) {
            return 1.0;
        }

        for ($i = 1, $total = count($parts); $i < $total; ++$i) {
            $param = trim($parts[$i]);

            if (1 === preg_match('/^q\s*=\s*(?<value>\d+(?:\.\d+)?)$/i', $param, $matches)) {
                return (float) $matches['value'];
            }
        }

        return 1.0;
    }
}
