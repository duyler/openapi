<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use function count;

readonly class MultipartBodyParser
{
    /**
     * @return list<array<array-key, mixed>>
     */
    public function parse(string $body): array
    {
        if ('' === trim($body)) {
            return [];
        }

        $parts = [];
        $boundary = $this->extractBoundary($body);

        if (null === $boundary) {
            return $parts;
        }

        $sections = explode('--' . $boundary, $body);

        foreach ($sections as $section) {
            if ('' === trim($section) || '--' === trim($section)) {
                continue;
            }

            $part = $this->parsePart($section);
            if (null !== $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    private function extractBoundary(string $body): ?string
    {
        if (preg_match('/boundary="?([^"\s]+)"?/i', substr($body, 0, 500), $matches)) {
            return $matches[1];
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
