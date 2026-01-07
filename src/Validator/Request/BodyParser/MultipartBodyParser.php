<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

final readonly class MultipartBodyParser
{
    /**
     * @return list<array<array-key, mixed>>
     */
    public function parse(string $body): array
    {
        if ('' === trim($body)) {
            return [];
        }

        // Note: Full multipart parsing is complex and typically handled by web frameworks
        // This is a simplified version for basic cases
        // In real-world scenarios, PSR-7 implementations provide parsed body
        $parts = [];
        $boundary = $this->extractBoundary($body);

        if (null === $boundary) {
            return $parts;
        }

        // Split by boundary
        $sections = explode('--' . $boundary, $body);

        foreach ($sections as $section) {
            if (empty(trim($section)) || '--' === trim($section)) {
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
        // Try to extract boundary from Content-Type header format
        // This is a simplified extraction
        if (preg_match('/boundary="?([^"\s]+)"?/i', substr($body, 0, 500), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function parsePart(string $section): ?array
    {
        // Split headers from body
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
