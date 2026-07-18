<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\PregExecutor;

use InvalidArgumentException;

use function count;
use function explode;
use function sprintf;
use function strtolower;
use function substr_count;
use function trim;

final readonly class ContentTypeNegotiator
{
    private const int MAX_CONTENT_TYPE_PARAMS = 20;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    public function getMediaType(string $contentType): string
    {
        if (substr_count($contentType, ';') + 1 > self::MAX_CONTENT_TYPE_PARAMS) {
            throw new InvalidArgumentException(
                sprintf('Maximum Content-Type parameters of %d exceeded', self::MAX_CONTENT_TYPE_PARAMS),
            );
        }

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
        for ($i = 1, $total = count($parts); $i < $total; ++$i) {
            $param = trim($parts[$i]);

            if (1 === $this->pregExecutor->match('/^q\s*=\s*(?<value>\d+(?:\.\d+)?)$/i', $param, $matches)) {
                return (float) $matches['value'];
            }
        }

        return 1.0;
    }
}
