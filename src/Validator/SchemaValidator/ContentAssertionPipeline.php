<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\ContentValidationResult;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\DecodedContentTooLargeException;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Duyler\OpenApi\Validator\SchemaValidator\Enum\ContentMediaType;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\EvaluatedMedia;
use Duyler\OpenApi\Validator\TypeFormatter;
use JsonException;
use SimpleXMLElement;

use function assert;
use function explode;
use function is_string;
use function json_decode;
use function preg_quote;
use function simplexml_load_string;
use function str_starts_with;
use function strtr;
use function substr_count;
use function strlen;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;
use const JSON_THROW_ON_ERROR;

final readonly class ContentAssertionPipeline
{
    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;

    private const int MAX_URLENCODED_PAIRS = 1000;

    private const int XML_PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

    public function __construct(
        private readonly SchemaValidatorDependencies $dependencies,
    ) {}

    public function assert(
        string $data,
        Schema $schema,
        ValidationContext $context,
        bool $skipMediaType = false,
    ): ContentValidationResult {
        return $this->run($data, $schema, $context, $skipMediaType, collectErrors: false);
    }

    public function defer(
        string $data,
        Schema $schema,
        ValidationContext $context,
        bool $skipMediaType = false,
    ): ContentValidationResult {
        return ContentValidationResult::lazy(
            fn(): ContentValidationResult => $this->run($data, $schema, $context, $skipMediaType, collectErrors: true),
        );
    }

    public function isRecognizedMediaType(string $mediaType): bool
    {
        return null !== ContentMediaType::tryFrom($mediaType);
    }

    private function run(
        string $data,
        Schema $schema,
        ValidationContext $context,
        bool $skipMediaType,
        bool $collectErrors,
    ): ContentValidationResult {
        $decoded = $this->decode($data, $schema->contentEncoding, $collectErrors);
        $media = $this->evaluateMedia($decoded, $schema->contentMediaType, $skipMediaType);
        $schemaErrors = $this->validateSubschema($media->content, $schema->contentSchema, $context, $media->isValid, $collectErrors);

        return ContentValidationResult::eager($media->content, $media->isValid, $schemaErrors);
    }

    private function decode(string $data, ?string $encoding, bool $collectErrors): ?string
    {
        if (null === $encoding) {
            return $this->guardDecodedSize($data, null);
        }

        $decoded = match ($encoding) {
            'base64' => $this->dependencies->contentDecoder->decodeBase64($data),
            'base64url' => $this->decodeBase64Url($data),
            default => $data,
        };

        if (false === is_string($decoded)) {
            if (false === $collectErrors) {
                throw new InvalidContentEncodingException($data, $encoding);
            }

            return null;
        }

        return $this->guardDecodedSize($decoded, $encoding);
    }

    private function decodeBase64Url(string $data): string|false
    {
        $translated = strtr($data, '-_', '+/');

        $translated .= match (strlen($translated) % 4) {
            2 => '==',
            3 => '=',
            default => '',
        };

        return $this->dependencies->contentDecoder->decodeBase64($translated);
    }

    private function guardDecodedSize(string $decoded, ?string $encoding): string
    {
        $size = strlen($decoded);

        if ($size <= $this->dependencies->maxDecodedContentBytes) {
            return $decoded;
        }

        throw new DecodedContentTooLargeException($size, $this->dependencies->maxDecodedContentBytes, $encoding);
    }

    private function evaluateMedia(?string $decoded, ?string $mediaType, bool $skipMediaType): EvaluatedMedia
    {
        if ($skipMediaType || null === $mediaType) {
            return new EvaluatedMedia(isValid: true, content: $decoded);
        }

        if (null === $decoded) {
            return new EvaluatedMedia(isValid: false, content: null);
        }

        return match ($mediaType) {
            'application/json' => $this->evaluateJson($decoded),
            'application/xml', 'text/xml', 'image/svg+xml' => new EvaluatedMedia($this->isValidXml($decoded), $decoded),
            'text/plain' => new EvaluatedMedia('' !== $decoded, $decoded),
            'text/html' => new EvaluatedMedia($this->isValidHtml($decoded), $decoded),
            'application/pdf' => new EvaluatedMedia(str_starts_with($decoded, '%PDF'), $decoded),
            'application/octet-stream' => new EvaluatedMedia('' !== $decoded, $decoded),
            'image/png' => new EvaluatedMedia(str_starts_with($decoded, "\x89PNG"), $decoded),
            'image/jpeg' => new EvaluatedMedia(str_starts_with($decoded, "\xFF\xD8\xFF"), $decoded),
            'image/gif' => new EvaluatedMedia(
                str_starts_with($decoded, 'GIF87a') || str_starts_with($decoded, 'GIF89a'),
                $decoded,
            ),
            'multipart/form-data' => new EvaluatedMedia($this->isValidMultipartFormData($decoded), $decoded),
            'application/x-www-form-urlencoded' => new EvaluatedMedia($this->isValidUrlEncoded($decoded), $decoded),
            default => new EvaluatedMedia($this->isRecognizedMediaType($mediaType), $decoded),
        };
    }

    private function evaluateJson(string $data): EvaluatedMedia
    {
        if ('' === $data) {
            return new EvaluatedMedia(isValid: false, content: null);
        }

        try {
            /** @var array<int|string, mixed>|int|string|float|bool|null $content */
            $content = json_decode($data, associative: true, depth: self::JSON_MAX_DEPTH, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new EvaluatedMedia(isValid: false, content: null);
        }

        return new EvaluatedMedia(isValid: true, content: $content);
    }

    /**
     * @param string|array<int|string, mixed>|int|float|bool|null $content
     * @param Schema|bool|null $contentSchema
     *
     * @return array<int, ValidationErrorInterface>
     */
    private function validateSubschema(
        string|array|int|float|bool|null $content,
        Schema|bool|null $contentSchema,
        ValidationContext $context,
        bool $mediaTypeValid,
        bool $collectErrors,
    ): array {
        if (null === $contentSchema || true === $contentSchema || null === $content || false === $mediaTypeValid) {
            return [];
        }

        if (false === $contentSchema) {
            return $this->rejectByBooleanFalseSchema($content, $context, $collectErrors);
        }

        $rootValidator = $this->dependencies->rootStatelessValidator();

        if (false === $collectErrors) {
            $rootValidator->validate($content, $contentSchema, $context);

            return [];
        }

        try {
            $rootValidator->validate($content, $contentSchema, $context);
        } catch (ValidationException $exception) {
            return $exception->getErrors();
        } catch (AbstractValidationError $error) {
            return [$error];
        }

        return [];
    }

    /**
     * @return list<ValidationErrorInterface>
     */
    private function rejectByBooleanFalseSchema(
        string|array|int|float|bool|null $content,
        ValidationContext $context,
        bool $collectErrors,
    ): array {
        $error = new TypeMismatchError(
            expected: 'nothing (boolean schema false)',
            actual: TypeFormatter::format($content),
            dataPath: $context->path(),
            schemaPath: '/contentSchema',
        );

        if (false === $collectErrors) {
            throw $error;
        }

        return [$error];
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

    private function isValidHtml(string $data): bool
    {
        if ('' === $data) {
            return false;
        }

        return 1 === $this->dependencies->pregExecutor->match('/<[a-zA-Z][^>]*>/', $data);
    }

    private function isValidMultipartFormData(string $data): bool
    {
        $matches = [];
        if (1 !== $this->dependencies->pregExecutor->match('/^--(?<boundary>[^\r\n]+)/', $data, $matches)) {
            return false;
        }

        $boundary = $matches['boundary'] ?? '';
        assert(is_string($boundary));
        $closingBoundary = '--' . $boundary . '--';

        return 1 === $this->dependencies->pregExecutor->match('/' . preg_quote($closingBoundary, '/') . '\s*$/', $data);
    }

    private function isValidUrlEncoded(string $data): bool
    {
        if ('' === $data) {
            return true;
        }

        if (self::MAX_URLENCODED_PAIRS < substr_count($data, '&') + 1) {
            return false;
        }
        return array_all(explode('&', $data), fn($pair) => 1 === $this->dependencies->pregExecutor->match('/^[^&=]+(?:=[^&]*)?$/', $pair));
    }
}
