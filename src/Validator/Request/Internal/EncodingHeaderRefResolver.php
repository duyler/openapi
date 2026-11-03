<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\OpenApiDocument;

use function array_map;
use function strlen;
use function str_starts_with;
use function substr;

/** @internal */
final readonly class EncodingHeaderRefResolver
{
    private const string HEADER_REF_PREFIX = '#/components/headers/';

    public function resolve(MediaType $mediaType, OpenApiDocument $document): MediaType
    {
        $encoding = $this->resolveEncodingMap($mediaType->encoding, $document);
        $prefixEncoding = $this->resolveEncodingList($mediaType->prefixEncoding, $document);
        $itemEncoding = $this->resolveEncoding($mediaType->itemEncoding, $document);

        if ($encoding === $mediaType->encoding
            && $prefixEncoding === $mediaType->prefixEncoding
            && $itemEncoding === $mediaType->itemEncoding
        ) {
            return $mediaType;
        }

        return new MediaType(
            schema: $mediaType->schema,
            itemSchema: $mediaType->itemSchema,
            encoding: $encoding,
            itemEncoding: $itemEncoding,
            prefixEncoding: $prefixEncoding,
            examples: $mediaType->examples,
            example: $mediaType->example,
        );
    }

    /**
     * @param array<string, Encoding>|null $encodings
     * @return array<string, Encoding>|null
     */
    private function resolveEncodingMap(?array $encodings, OpenApiDocument $document): ?array
    {
        if (null === $encodings) {
            return null;
        }

        $resolved = [];

        foreach ($encodings as $name => $encoding) {
            $resolved[$name] = $this->resolveEncodingObject($encoding, $document);
        }

        return $resolved;
    }

    /**
     * @param array<int, Encoding>|null $encodings
     * @return array<int, Encoding>|null
     */
    private function resolveEncodingList(?array $encodings, OpenApiDocument $document): ?array
    {
        if (null === $encodings) {
            return null;
        }

        $resolved = [];

        foreach ($encodings as $encoding) {
            $resolved[] = $this->resolveEncodingObject($encoding, $document);
        }

        return $resolved;
    }

    private function resolveEncoding(?Encoding $encoding, OpenApiDocument $document): ?Encoding
    {
        if (null === $encoding) {
            return null;
        }

        return $this->resolveEncodingObject($encoding, $document);
    }

    private function resolveEncodingObject(Encoding $encoding, OpenApiDocument $document): Encoding
    {
        $headers = $this->resolveHeaders($encoding->headers, $document);
        $nested = $this->resolveEncodingMap($encoding->encoding, $document);
        $prefixEncoding = $this->resolveEncodingList($encoding->prefixEncoding, $document);
        $itemEncoding = $this->resolveEncoding($encoding->itemEncoding, $document);

        if ($headers === $encoding->headers
            && $nested === $encoding->encoding
            && $prefixEncoding === $encoding->prefixEncoding
            && $itemEncoding === $encoding->itemEncoding
        ) {
            return $encoding;
        }

        return new Encoding(
            contentType: $encoding->contentType,
            headers: $headers,
            style: $encoding->style,
            explode: $encoding->explode,
            allowReserved: $encoding->allowReserved,
            encoding: $nested,
            prefixEncoding: $prefixEncoding,
            itemEncoding: $itemEncoding,
        );
    }

    private function resolveHeaders(?Headers $headers, OpenApiDocument $document): ?Headers
    {
        if (null === $headers) {
            return null;
        }

        $resolved = array_map(fn(Header $header): Header => $this->resolveHeader($header, $document), $headers->headers);

        if ($resolved === $headers->headers) {
            return $headers;
        }

        return new Headers($resolved);
    }

    private function resolveHeader(Header $header, OpenApiDocument $document): Header
    {
        if (null === $header->ref) {
            return $header;
        }

        return $this->componentHeader($header->ref, $document) ?? $header;
    }

    private function componentHeader(string $ref, OpenApiDocument $document): ?Header
    {
        if (false === str_starts_with($ref, self::HEADER_REF_PREFIX)) {
            return null;
        }

        $name = substr($ref, strlen(self::HEADER_REF_PREFIX));

        return $document->components?->headers[$name] ?? null;
    }
}
