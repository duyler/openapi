<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Validator\Exception\MissingPartHeaderError;
use Duyler\OpenApi\Validator\Exception\PartContentTypeMismatchError;
use Duyler\OpenApi\Validator\ExternalMediaType;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;

use function array_key_exists;
use function count;
use function explode;
use function implode;
use function is_array;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function str_replace;
use function strtolower;
use function substr;
use function trim;

final readonly class MultipartEncodingApplier
{
    private const int MAX_NESTING_DEPTH = 1;

    private const string CONTENT_TYPE_HEADER = 'content-type';

    private const string CONTENT_DISPOSITION_HEADER = 'content-disposition';

    private const string PART_NAME_PATTERN = '/(?:^|;)\s*name="(?<name>[^"]*)"/i';

    private const string KEYWORD_ENCODING = 'encoding';

    private const string KEYWORD_PREFIX_ENCODING = 'prefixEncoding';

    private const string KEYWORD_ITEM_ENCODING = 'itemEncoding';

    public function __construct(
        private readonly MultipartBodyParser $multipartParser = new MultipartBodyParser(),
    ) {}

    /**
     * @param array<int, Encoding>|null $prefixEncoding
     * @param array<string, Encoding>|null $encoding
     */
    public function applyToBody(
        string $body,
        string $fullContentType,
        ?array $prefixEncoding = null,
        ?Encoding $itemEncoding = null,
        ?array $encoding = null,
    ): void {
        if (null === $prefixEncoding && null === $itemEncoding && null === $encoding) {
            return;
        }

        $this->applyParts(
            parts: $this->multipartParser->parse($body, $fullContentType),
            prefixEncoding: $prefixEncoding,
            itemEncoding: $itemEncoding,
            encoding: $encoding,
            depth: 0,
        );
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<int, Encoding>|null $prefixEncoding
     * @param array<string, Encoding>|null $encoding
     */
    public function applyToParts(
        array $parts,
        ?array $prefixEncoding = null,
        ?Encoding $itemEncoding = null,
        ?array $encoding = null,
    ): void {
        $this->applyParts(
            parts: $parts,
            prefixEncoding: $prefixEncoding,
            itemEncoding: $itemEncoding,
            encoding: $encoding,
            depth: 0,
        );
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<int, Encoding>|null $prefixEncoding
     * @param array<string, Encoding>|null $encoding
     */
    private function applyParts(
        array $parts,
        ?array $prefixEncoding,
        ?Encoding $itemEncoding,
        ?array $encoding,
        int $depth,
    ): void {
        foreach ($parts as $index => $part) {
            $headers = $this->headerMap($part['headers']);
            $resolved = $this->resolveEncoding($prefixEncoding, $itemEncoding, $encoding, $index, $headers);

            if (null === $resolved) {
                continue;
            }

            $partEncoding = $resolved['encoding'];
            $this->assertContentType($partEncoding, $headers, $index, $resolved['keyword']);
            $this->assertRequiredHeaders($partEncoding->headers, $headers, $index);
            $this->applyNested($part['content'], $partEncoding, $headers, $depth);
        }
    }

    /**
     * @param array<int, Encoding>|null $prefixEncoding
     * @param array<string, Encoding>|null $encoding
     * @param array<string, string> $headers
     * @return array{encoding: Encoding, keyword: string}|null
     */
    private function resolveEncoding(
        ?array $prefixEncoding,
        ?Encoding $itemEncoding,
        ?array $encoding,
        int $index,
        array $headers,
    ): ?array {
        if (null !== $prefixEncoding && array_key_exists($index, $prefixEncoding)) {
            return ['encoding' => $prefixEncoding[$index], 'keyword' => self::KEYWORD_PREFIX_ENCODING];
        }

        if (null !== $itemEncoding) {
            return ['encoding' => $itemEncoding, 'keyword' => self::KEYWORD_ITEM_ENCODING];
        }

        if (null !== $encoding) {
            $name = $this->partName($headers);

            if (null !== $name && array_key_exists($name, $encoding)) {
                return ['encoding' => $encoding[$name], 'keyword' => self::KEYWORD_ENCODING];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private function assertContentType(
        Encoding $partEncoding,
        array $headers,
        int $index,
        string $keyword,
    ): void {
        $expected = $partEncoding->contentType;

        if (null === $expected || [] === $expected) {
            return;
        }

        $actualHeader = $headers[self::CONTENT_TYPE_HEADER] ?? null;

        if (null === $actualHeader) {
            return;
        }

        $actual = $this->mediaTypeOf($actualHeader);
        $candidates = is_array($expected) ? $expected : [$expected];

        foreach ($candidates as $candidate) {
            if ($this->mediaTypeMatches($actual, $candidate)) {
                return;
            }
        }

        throw new PartContentTypeMismatchError(
            keyword: $keyword,
            expected: implode(', ', $candidates),
            actual: $actual,
            partIndex: $index,
        );
    }

    /**
     * @param array<string, string> $partHeaders
     */
    private function assertRequiredHeaders(?Headers $headers, array $partHeaders, int $index): void
    {
        if (null === $headers) {
            return;
        }

        foreach ($headers->headers as $name => $header) {
            $lowerName = strtolower($name);

            if (self::CONTENT_TYPE_HEADER === $lowerName) {
                continue;
            }

            if ($header->required && false === array_key_exists($lowerName, $partHeaders)) {
                throw new MissingPartHeaderError(headerName: $name, partIndex: $index);
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function applyNested(string $content, Encoding $partEncoding, array $headers, int $depth): void
    {
        if ($depth + 1 > self::MAX_NESTING_DEPTH) {
            return;
        }

        if (null === $partEncoding->prefixEncoding
            && null === $partEncoding->itemEncoding
            && null === $partEncoding->encoding
        ) {
            return;
        }

        $nestedContentType = $this->nestedContentType($partEncoding, $headers);

        if (null === $nestedContentType) {
            return;
        }

        $this->applyParts(
            parts: $this->multipartParser->parse($content, $nestedContentType),
            prefixEncoding: $partEncoding->prefixEncoding,
            itemEncoding: $partEncoding->itemEncoding,
            encoding: $partEncoding->encoding,
            depth: $depth + 1,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function nestedContentType(Encoding $partEncoding, array $headers): ?string
    {
        $fromHeader = $headers[self::CONTENT_TYPE_HEADER] ?? null;

        if (null !== $fromHeader) {
            if (str_starts_with($this->mediaTypeOf($fromHeader), 'multipart/')) {
                return $fromHeader;
            }

            return null;
        }

        $fromEncoding = ExternalMediaType::primary($partEncoding->contentType, '');

        if ('' !== $fromEncoding && str_starts_with(strtolower(trim($fromEncoding)), 'multipart/')) {
            return $fromEncoding;
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private function partName(array $headers): ?string
    {
        $disposition = $headers[self::CONTENT_DISPOSITION_HEADER] ?? null;

        if (null === $disposition) {
            return null;
        }

        if (1 === preg_match(self::PART_NAME_PATTERN, $disposition, $matches)) {
            return $matches['name'];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headerMap(string $rawHeaders): array
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

    private function mediaTypeOf(string $headerValue): string
    {
        return strtolower(trim(explode(';', $headerValue, 2)[0]));
    }

    private function mediaTypeMatches(string $actual, string $expected): bool
    {
        $candidate = strtolower(trim($expected));

        if ('*/*' === $candidate || $candidate === $actual) {
            return true;
        }

        return str_ends_with($candidate, '/*') && str_starts_with($actual, substr($candidate, 0, -1));
    }
}
