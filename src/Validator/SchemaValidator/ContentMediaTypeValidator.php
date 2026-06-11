<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function in_array;
use function is_string;
use function str_contains;

use const JSON_ERROR_NONE;

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
            'text/plain', 'text/html' => true,
            'application/pdf' => str_contains($data, '%PDF'),
            'image/png' => str_contains($data, 'PNG'),
            'image/jpeg' => str_contains($data, 'JFIF') || str_contains($data, 'Exif'),
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

        libxml_use_internal_errors(true);
        $result = simplexml_load_string($data);
        libxml_clear_errors();

        return false !== $result;
    }

    private function isRecognizedMediaType(string $mediaType): bool
    {
        return in_array($mediaType, self::SUPPORTED_MEDIA_TYPES, true);
    }
}
