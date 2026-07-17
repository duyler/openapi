<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use RuntimeException;

use function count;
use function sprintf;

use function substr_count;

use const PREG_UNMATCHED_AS_NULL;

final readonly class MultipartBodyParser
{
    private const string BOUNDARY_PATTERN = '/boundary=(?:"(?<quoted>[^"]+)"|(?<unquoted>[^\s;]+))/i';

    private const int MAX_MULTIPART_PARTS = 1000;

    /**
     * @return list<array<array-key, mixed>>
     */
    public function parse(string $body, string $fullContentType = ''): array
    {
        if ('' === trim($body)) {
            return [];
        }

        $boundary = $this->extractBoundaryFromContentType($fullContentType)
            ?? $this->inferBoundaryFromBody($body);

        if (null === $boundary) {
            return [];
        }

        $delimiter = '--' . $boundary;

        if (substr_count($body, $delimiter) + 1 > self::MAX_MULTIPART_PARTS) {
            throw new RuntimeException(
                sprintf('Maximum multipart parts of %d exceeded', self::MAX_MULTIPART_PARTS),
            );
        }

        $parts = [];
        $sections = explode($delimiter, $body);

        foreach ($sections as $section) {
            $trimmed = trim($section);
            if ('' === $trimmed || '--' === $trimmed) {
                continue;
            }

            $part = $this->parsePart($section);
            if (null !== $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    private function extractBoundaryFromContentType(string $contentType): ?string
    {
        if (1 === preg_match(self::BOUNDARY_PATTERN, $contentType, $matches, PREG_UNMATCHED_AS_NULL)) {
            return $matches['quoted'] ?? $matches['unquoted'];
        }

        return null;
    }

    private function inferBoundaryFromBody(string $body): ?string
    {
        if (1 === preg_match('/^--(?<boundary>[^\r\n]+)/', $body, $matches)) {
            return $matches['boundary'];
        }

        return null;
    }

    private function parsePart(string $section): ?array
    {
        $parts = explode("\r\n\r\n", $section, 2);

        if (2 !== count($parts)) {
            return null;
        }

        [$headers, $content] = $parts;

        return [
            'headers' => $headers,
            'content' => $content,
        ];
    }
}
