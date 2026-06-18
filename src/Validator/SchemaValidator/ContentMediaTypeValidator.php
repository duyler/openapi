<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Override;
use SimpleXMLElement;

use function in_array;
use function is_string;
use function preg_match;
use function str_starts_with;

use const JSON_ERROR_NONE;
use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

final readonly class ContentMediaTypeValidator implements SchemaValidatorInterface
{
    private const array SUPPORTED_MEDIA_TYPES = [
        'application/json',
        'application/xml',
        'text/plain',
        'text/html',
        'text/xml',
        'application/pdf',
        'application/octet-stream',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/svg+xml',
        'multipart/form-data',
        'application/x-www-form-urlencoded',
    ];

    private const int XML_PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contentMediaType || false === is_string($data)) {
            return;
        }

        $decodedData = $this->decodeIfNeeded($data, $schema->contentEncoding);

        if (false === $decodedData) {
            return;
        }

        $this->validateMediaTypeSyntax($decodedData, $schema->contentMediaType);
    }

    private function decodeIfNeeded(string $data, ?string $encoding): string|false
    {
        if (null === $encoding) {
            return $data;
        }

        return match ($encoding) {
            'base64' => base64_decode($data, strict: true),
            default => $data,
        };
    }

    private function validateMediaTypeSyntax(string $data, string $expectedMediaType): void
    {
        $isValid = match ($expectedMediaType) {
            'application/json' => $this->isValidJson($data),
            'application/xml', 'text/xml' => $this->isValidXml($data),
            'text/plain' => $this->isValidTextPlain($data),
            'text/html' => $this->isValidHtml($data),
            'application/pdf' => $this->isValidPdf($data),
            'application/octet-stream' => $this->isValidOctetStream($data),
            'image/png' => $this->isValidPng($data),
            'image/jpeg' => $this->isValidJpeg($data),
            'image/gif' => $this->isValidGif($data),
            'image/svg+xml' => $this->isValidSvg($data),
            'multipart/form-data' => $this->isValidMultipartFormData($data),
            'application/x-www-form-urlencoded' => $this->isValidUrlEncoded($data),
            default => $this->isRecognizedMediaType($expectedMediaType),
        };

        if (false === $isValid) {
            throw new InvalidContentMediaTypeException($data, $expectedMediaType);
        }
    }

    private function isValidJson(string $data): bool
    {
        if ('' === $data) {
            return false;
        }

        json_decode($data);

        return JSON_ERROR_NONE === json_last_error();
    }

    private function isValidXml(string $data): bool
    {
        if ('' === $data) {
            return false;
        }

        $result = LibxmlSecuredContext::run(
            static fn(): SimpleXMLElement|false => simplexml_load_string(
                $data,
                options: self::XML_PARSE_OPTIONS,
            ),
        );

        return false !== $result;
    }

    private function isValidTextPlain(string $data): bool
    {
        return '' !== $data;
    }

    private function isValidHtml(string $data): bool
    {
        if ('' === $data) {
            return false;
        }

        return 1 === preg_match('/<[a-zA-Z][^>]*>/', $data);
    }

    private function isValidPdf(string $data): bool
    {
        return str_starts_with($data, '%PDF');
    }

    private function isValidOctetStream(string $data): bool
    {
        return '' !== $data;
    }

    private function isValidPng(string $data): bool
    {
        return str_starts_with($data, "\x89PNG");
    }

    private function isValidJpeg(string $data): bool
    {
        return str_starts_with($data, "\xFF\xD8\xFF");
    }

    private function isValidGif(string $data): bool
    {
        return str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a');
    }

    private function isValidSvg(string $data): bool
    {
        return $this->isValidXml($data);
    }

    private function isValidMultipartFormData(string $data): bool
    {
        return 1 === preg_match('/^--[^\r\n]+/', $data);
    }

    private function isValidUrlEncoded(string $data): bool
    {
        if ('' === $data) {
            return true;
        }

        return 1 === preg_match('/^[^&=]+=[^&]*(&[^&=]+=[^&]*)*$/', $data);
    }

    private function isRecognizedMediaType(string $mediaType): bool
    {
        return in_array($mediaType, self::SUPPORTED_MEDIA_TYPES, true);
    }
}
