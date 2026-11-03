<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\MultipartBodyTooLargeException;

use function count;
use function explode;
use function preg_match;
use function substr_count;
use function trim;

use const PREG_UNMATCHED_AS_NULL;

final readonly class MultipartBodyParser
{
    public const int MAX_MULTIPART_PARTS = 1000;
    private const string BOUNDARY_PATTERN = '/boundary=(?:"(?<quoted>[^"]+)"|(?<unquoted>[^\s;]+))/i';

    /**
     * @return list<array{headers: string, content: string}>
     */
    public function parse(string $body, string $fullContentType = ''): array
    {
        if ('' === trim($body)) {
            return [];
        }

        $boundary = self::boundaryFromContentType($fullContentType) ?? self::boundaryFromBody($body);

        if (null === $boundary) {
            return [];
        }

        $delimiter = '--' . $boundary;

        if (substr_count($body, $delimiter) + 1 > self::MAX_MULTIPART_PARTS) {
            throw new MultipartBodyTooLargeException(self::MAX_MULTIPART_PARTS);
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

    public static function boundaryFromContentType(string $contentType): ?string
    {
        if (1 === preg_match(self::BOUNDARY_PATTERN, $contentType, $matches, PREG_UNMATCHED_AS_NULL)) {
            return $matches['quoted'] ?? $matches['unquoted'];
        }

        return null;
    }

    public static function boundaryFromBody(string $body): ?string
    {
        if (1 === preg_match('/^--(?<boundary>[^\r\n]+)/', $body, $matches)) {
            return $matches['boundary'];
        }

        return null;
    }

    /**
     * @return array{headers: string, content: string}|null
     */
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
